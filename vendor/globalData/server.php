<?php

namespace vendor\globalData;

use Workerman\Worker;

/**
 * Global data server.
 */
class server
{
    /**
     * Worker instance.
     * @var worker
     */
    protected $_worker = null;

    /**
     * All data.
     * @var array
     */
    protected $_dataArray = array();

    /**
     * server constructor.
     * @param string $socket
     * @param string $name
     * @param int $count
     */
    public function __construct($socket = '0.0.0.0:6666', $name = 'global', $count = 1)
    {
        $worker = new Worker("frame://$socket");
        $worker->name = $name;
        $worker->count = $count;
        $worker->onMessage = array($this, 'onMessage');
        $worker->reloadable = false;
        $this->_worker = $worker;
    }

    /**
     * onMessage.
     * @param TcpConnection $connection
     * @param string $buffer
     */
    public function onMessage($connection, $buffer)
    {
        if ($buffer === 'ping') {
            return;
        }
        $data = unserialize($buffer);
        if (!$buffer || !isset($data['cmd']) || !isset($data['key'])) {
            return $connection->close(serialize('bad request'));
        }
        $cmd = $data['cmd'];
        $key = $data['key'];
        switch ($cmd) {
            case 'get':
                if (!isset($this->_dataArray[$key])) {
                    $connection->send('N;');
                    break;
                }
                return $connection->send(serialize($this->_dataArray[$key]));
                break;
            case 'set':
                $this->_dataArray[$key] = $data['value'];
                $connection->send('b:1;');
                break;
            case 'add':
                if (isset($this->_dataArray[$key])) {
                    $connection->send('b:0;');
                    break;
                }
                $this->_dataArray[$key] = $data['value'];
                $connection->send('b:1;');
                break;
            case 'increment':
                if (!isset($this->_dataArray[$key])) {
                    $connection->send('b:0;');
                    break;
                }
                if (!is_numeric($this->_dataArray[$key])) {
                    $this->_dataArray[$key] = 0;
                }
                $this->_dataArray[$key] = $this->_dataArray[$key] + $data['step'];
                $connection->send(serialize($this->_dataArray[$key]));
                break;
            case 'cas':
                $old_value = !isset($this->_dataArray[$key]) ? null : $this->_dataArray[$key];
                if (md5(serialize($old_value)) === $data['md5']) {
                    $this->_dataArray[$key] = $data['value'];
                    $connection->send('b:1;');
                    break;
                }
                $connection->send('b:0;');
                break;
            case 'delete':
                unset($this->_dataArray[$key]);
                $connection->send('b:1;');
                break;
            case 'hSet':
                $this->_dataArray[$key][$data['hKey']] = $data['value'];
                $connection->send('b:1;');
                break;
            case 'hSetField':
                if (isset($this->_dataArray[$key][$data['hKey']][$data['field']])) {
                    $this->_dataArray[$key][$data['hKey']][$data['field']] = $data['value'];
                    $connection->send('b:1;');
                    break;
                }
                $connection->send('b:0;');
                break;
            case 'hGet':
                if (isset($this->_dataArray[$key][$data['hKey']])) {
                    $connection->send(serialize($this->_dataArray[$key][$data['hKey']]));
                    break;
                }
                $connection->send('N;');
                break;
            case 'hGetField':
                if (isset($this->_dataArray[$key][$data['hKey']][$data['field']])) {
                    $connection->send($this->_dataArray[$key][$data['hKey']][$data['field']]);
                    break;
                }
                $connection->send('N;');
                break;
            case 'hPageGet':
                if (isset($this->_dataArray[$key])) {
                    $result = array_slice($this->_dataArray[$key], $data['start'], $data['length'], true);
                    return $connection->send(serialize($result));
                }
                $connection->send('a:0:{}');
                break;
            case 'hGetAll':
                if (isset($this->_dataArray[$key])) {
                    $connection->send(serialize($this->_dataArray[$key]));
                    break;
                }
                $connection->send('a:0:{}');
                break;
            case 'hLen':
                if (isset($this->_dataArray[$key])) {
                    $connection->send(serialize(count($this->_dataArray[$key])));
                    break;
                }
                $connection->send('i:0;');
                break;
            case 'hDel':
                unset($this->_dataArray[$key][$data['hKey']]);
                $connection->send('b:1;');
                break;
            default:
                $connection->close(serialize('bad cmd ' . $cmd));
                break;
        }
    }
}


