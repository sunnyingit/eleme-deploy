<?php

App::before(function($request){
    $preg = '/^\/github\/oauth|\/logout|\/playload/';
    $check =  GithubLogin::check();
    if (!$check && preg_match($preg, $request->getRequestUri()) == 0) {
        return Redirect::to('/github/oauth/confirm');
    } else if($check) {
        $user = GithubLogin::getLoginUser();
        View::share('login', $user->login);
    }
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::post('/playload', function() {
    if (Request::header('X-GitHub-Event') == 'pull_request') {
        $content = file_get_contents('php://input');
        $notifyObject = json_decode($content);
        $repoFullName = $notifyObject->pull_request->base->repo->full_name;
        $siteId = WebSite::getSiteIdByFullName($repoFullName);
        $commit = $notifyObject->pull_request->head->sha;
        if ($siteId !== NULL) {
            $pr = new PullRequest($siteId);
            $have = $pr->get($commit);
            if($have !== NULL) {
                if($notifyObject->action == 'closed'){
                    $mgb = $notifyObject->pull_request->merged_by;
                    $have->mergedBy = empty($mgb) ? '' : $mgb->login;
                    $commitList = $pr->getListByPRId($notifyObject->pull_request->id);
                    foreach ($commitList as $m) {
                        $m->status = 'closed';
                        $pr->save($m);
                    }
                } else if($notifyObject->action == 'reopened') {
                    $commitList = $pr->getListByPRId($notifyObject->pull_request->id);
                    foreach ($commitList as $m) {
                        $m->status = 'open';
                        $pr->save($m);
                    }
                }
                $have->status = $notifyObject->pull_request->state;
                $pr->save($have);
            } else {
                Queue::push('PullRequestBuild', array('siteId' => $siteId, 'commit' => $commit), DeployInfo::BUILD_QUEUE);
                $pr->add($notifyObject);
            }
        }
    }
    return  "";
});

Route::get('/github/oauth/confirm', function(){
    if (GithubLogin::check()) {
        return Redirect::to('/');
    }
    return Response::view('oauth-confirm');
});

Route::get('/github/oauth', function(){
    if (GithubLogin::check()) {
        return Redirect::to('/');
    }
    return Redirect::to(GithubLogin::authorizeUrl());
});

Route::get('/logout', function(){
    $cookie = GithubLogin::logout();
    return Redirect::to('/github/oauth/confirm')->withCookie($cookie);
});

Route::get('/github/oauth/callback', function(){
    if (GithubLogin::check()) {
        return Redirect::to('/');
    }

    $code = Input::get('code');
    if ($code == '') {
        return 'CODE ERROR';
    }

    $accessToken = \Eleme\Github\GithubAuthorize::accessToken($code);
    if ($accessToken == NULL) {
        echo "CODE ERROR";
    }
    $client = new \Eleme\Github\GithubClient($accessToken);
    $teams = $client->request('user/teams');
    $haveEleme = false;
    $orgTeams = array();
    foreach ($teams as $team) {
        if ($team->organization->login == Config::get('github.organization')) {
            $haveEleme = true;
            $orgTeams[] = new GithubTeam($team);
        }
    }

    if ($haveEleme) {
        $user = $client->request('user');
        $cookie = GithubLogin::login($user->login, $user->email, $accessToken, $orgTeams);

        return Redirect::to('/')->withCookie($cookie);

    } else {
        return "ORG ERROR";
    }
});

Route::get('/user/team/repos', function(){
    $repos = array();
    $user = GithubLogin::getLoginUser();
    foreach ($user->teams as $team) {
        $repos = array_merge($repos, (new TeamRepos($team->id))->repos());
    }
    //var_dump($repos);
    return Response::json(array('res' => 0, 'data' => $repos));
});

Route::get('/', 'SystemController@index');

Route::post('/system/config/save', 'SystemController@systemConfig');

Route::post('/hostType/add', 'SystemController@addHostType');
Route::post('/hostType/del', 'SystemController@delHostType');

Route::post('/site/add', 'SystemController@addSite');
Route::post('/site/del', 'SystemController@delSite');

Route::post('/config/save', 'ConfigController@saveConfig');

Route::get('/test', function(){
    return '';
});



Route::get('/site/config/{siteId}', 'ConfigController@config');

Route::get('/deploy/{siteId}', 'DeployController@index');

Route::get('/host/config/{siteId}', 'ConfigController@hostConfig');
Route::post('/host/add', 'ConfigController@hostAdd');
Route::post('/host/del', 'ConfigController@hostDel');


Route::post('/branch/deploy', 'DeployController@branch');
Route::post('/commit/deploy', 'DeployController@commit');
Route::get('/status/deploy', 'DeployController@status');

// PR
Route::get('/{siteId}/pull_request/info', 'PullRequestController@info');
Route::get('/{siteId}/pull_request/deploy', 'PullRequestController@deploy');
Route::get('/{siteId}/status/pull_request/build', 'PullRequestController@buildStatus');
Route::post('/{siteId}/pull_request/rebuild', 'PullRequestController@rebuild');