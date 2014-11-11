<?php
/**
 * Created by PhpStorm.
 * User: heimonsy
 * Date: 11/8/14
 * Time: 3:03 PM
 */

class GithubUser
{

    static private $keyPrefix = 'DEPLOY:S:LOGIN:';

    private $redis;
    private $expires;

    public $login;
    public $email;
    public $token;
    public $teams;
    public $permissions;


    public function __construct($login, $email, $token, $teams){
        $this->login = $login;
        $this->email = $email;
        $this->token = $token;
        $this->teams = $teams;
        $this->sitePermission();

        $this->redis = app('redis')->connection();
        $this->expires = 60 * 60 * 24;
    }

    public function set()
    {
        return $this->redis->set($this->key(), $this->json(), 'EX', $this->expires);
    }

    public function json()
    {
        return json_encode($this);
    }

    public function key()
    {
        return self::$keyPrefix . $this->login;
    }


    public static function loadFromRedis($login)
    {
        $jstr = app('redis')->connection()->get(self::$keyPrefix . $login);
        return self::loadFromJson($jstr);
    }

    public static function loadFromJson($jstr)
    {
        if (empty($jstr)) {
            return NULL;
        }
        $jsonObject = json_decode($jstr);
        return new GithubUser($jsonObject->login, $jsonObject->email, $jsonObject->token, $jsonObject->teams);
    }

    private function sitePermission()
    {
        $siteList = (new WebSite())->getList();
        $pattern = '/:([\w\d]+\/[\w\d]+)\.git$/i';
        $this->permissions = array();
        foreach ($siteList as $m) {
            $dc = new DC($m['siteId']);
            if (preg_match($pattern, $dc->get(DC::GIT_ORIGIN), $matchs)) {
                $this->permissions[$m['siteId']] = $this->maxPermissionOfRepo($matchs[1]);
            }
        }
    }


    public function maxPermissionOfRepo($repoFullName)
    {
        $maxPermission = DeployPermissions::DENY;
        foreach ($this->teams as $team) {
            $repos = new TeamRepos($team->id);
            foreach ($repos->repos() as $repo) {
                if ($repo->fullName == $repoFullName && DeployPermissions::havePermission($maxPermission, $team->permission)) {
                    $maxPermission = $team->permission;
                }
            }
        }

        return $maxPermission;
    }

}