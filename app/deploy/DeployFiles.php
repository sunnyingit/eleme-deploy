<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 14-10-26
 * Time: 下午8:24
 */

use Symfony\Component\Process\Process;
use Illuminate\Remote\Connection;

class DeployFiles
{
    public static function deploy($commit, $hosttype)
    {
        Log::info('deploy files, commit id : ' . $commit);

        $redis = app('redis')->connection();
        $res = $redis->lrange('deploy.L.static.hosts.'.$hosttype, 0, -1);
        $static_hosts = array();
        foreach($res as $m) {
            $static_hosts[] = json_decode($m, true);
        }
//        $ssh_key = $redis->get('deploy.ssh.key');
//        $key_phrase = $redis->get('deploy.ssh.key.phrase');
        $username = $redis->get('deploy.remote.user');
        $root = $redis->get('deploy.root') . '/commit';
        $static_dir = $redis->get('deploy.static.dir');
        $remote_owner = $redis->get('deploy.remote.owner');

        $commit_path = "{$root}/{$commit}";


        $RSYNC_EXCLUDE = "{$commit_path}/rsync_exclude.conf";
        $REMOTE_STATIC_DIR = $redis->get('deploy.remote.static.dir');
        $LOCAL_STATIC_DIR = "{$commit_path}/$static_dir";

        $remoteProcess = function ($target, $script) {
            $script = 'set -e'.PHP_EOL.$script;
            return 'ssh '.$target.' \'bash -s\' << EOF
'.$script.'
EOF';
        };

        foreach ($static_hosts as $host) {
            $name = $host['hostname'];
            Log::info("deploying static files to {$name}.");
            (new Process($remoteProcess($name, "sudo mkdir -p {$REMOTE_STATIC_DIR}")))->mustRun();
            (new Process($remoteProcess($name, "sudo chown {$username} -R {$REMOTE_STATIC_DIR}")))->mustRun();
            (new Process("rsync -az --progress --force --delay-updates --exclude-from={$RSYNC_EXCLUDE} {$LOCAL_STATIC_DIR}/ {$name}:{$REMOTE_STATIC_DIR}/", $commit_path))->setTimeout(600)->mustRun();
            (new Process($remoteProcess($name, "sudo chown {$remote_owner} -R {$REMOTE_STATIC_DIR}")))->mustRun();
        }

        $res = $redis->lrange('deploy.L.web.hosts.'.$hosttype, 0, -1);
        $web_hosts = array();
        foreach($res as $m) {
            $web_hosts[] = json_decode($m, true);
        }
        $REMOTE_DIR = $redis->get('deploy.remote.app.dir');
        $LOCAL_DIR = $commit_path;
        $service_name = $redis->get('deploy.service.name');

        foreach ($web_hosts as $host) {
            $name = $host['hostname'];
            Log::info("deploying web apps to {$name}.");
            (new Process($remoteProcess($name, "sudo service {$service_name} stop")))->mustRun();
            (new Process($remoteProcess($name, "sudo mkdir -p {$REMOTE_DIR}")))->mustRun();
            (new Process($remoteProcess($name, "sudo chown {$username} -R {$REMOTE_DIR}")))->mustRun();
            (new Process("rsync -azq --progress --force --delete --delay-updates --exclude-from={$RSYNC_EXCLUDE} {$LOCAL_DIR}/ {$name}:{$REMOTE_DIR}/", $commit_path))->setTimeout(600)->mustRun();
            (new Process($remoteProcess($name, "sudo chown {$remote_owner} -R {$REMOTE_DIR}")))->mustRun();
            (new Process($remoteProcess($name, "sudo service {$service_name} start")))->mustRun();
        }
    }
}