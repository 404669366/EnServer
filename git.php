<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/5/21
 * Time: 9:09
 */
while (true) {
    var_dump(exec('git fetch --all && git reset --hard origin/master && git pull', $output));
    sleep(6);
}