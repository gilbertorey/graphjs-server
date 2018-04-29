<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphJS\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use Valitron\Validator;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\UserOut\Star;
use Pho\Lib\Graph\ID;


/**
 * Takes care of Content
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class ContentController extends AbstractController
{
    /**
     * Star 
     * 
     * [url]
     * 
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * @param string   $id
     * 
     * @return void
     */
    public function star(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['url']);
        $v->rule('url', ['url']);
        if(!$v->validate()) {
            $this->fail($response, "Url required.");
            return;
        }
        $i = $kernel->gs()->node($id);  
        $page = $this->_fromUrlToNode($kernel, $data["url"]);
        $i->star($page);    
        $this->succeed(
            $response, [
            "count" => count($page->getStarrers())
            ]
        );
    }
 
    protected function _fromUrlToNode(Kernel $kernel, string $url) 
    {
        $get_title = function(string $url){ // via https://stackoverflow.com/questions/4348912/get-title-of-website-via-link
            $str = file_get_contents($url);
            if(strlen($str)>0){
              $str = trim(preg_replace('/\s+/', ' ', $str)); // supports line breaks inside <title>
              preg_match("/\<title\>(.*)\<\/title\>/i",$str,$title); // ignore case
              return html_entity_decode($title[1]);
            }
        };
        $res = $kernel->index()->query("MATCH (n:page {Url: {url}}) RETURN n", ["url"=>$url]);
        if(count($res->results())==0) {
            return $kernel->founder()->post($url, $get_title($url));
        }
        return $kernel->gs()->node($res->results()[0]["udid"]);
    }
 
    public function isStarred(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['url']);
        $v->rule('url', ['url']);
        if(!$v->validate()) {
            $this->fail($response, "Url required.");
            return;
        }
          $page = $this->_fromUrlToNode($kernel, $data["url"]);
          $starrers = $page->getStarrers();
          $me= $session->get($request, "id");
          $this->succeed(
              $response, [
              "count"=>count($starrers), 
              "starred"=>is_null($me) ? false : $page->hasStarrer(ID::fromString($me))]
          );
    }


    public function comment(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['url', 'content']);
        $v->rule('url', ['url']);
        if(!$v->validate()) {
            $this->fail($response, "Url and content fields are required.");
            return;
        }
        $i = $kernel->gs()->node($id);  
         $page = $this->_fromUrlToNode($kernel, $data["url"]);
         $comment = $i->comment($page, $data["content"]);
         $this->succeed($response, ["comment_id"=>$comment->id()->toString()]);
    }

    public function fetchComments(Request $request, Response $response, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['url']);
        $v->rule('url', ['url']);
        if(!$v->validate()) {
            $this->fail($response, "Url field is required.");
            return;
        }
         $page = $this->_fromUrlToNode($kernel, $data["url"]);
         $comments = array_map(
                function ($val) { 
                    $ret = [];
                    $attributes = $val->attributes()->toArray();
                    foreach($attributes as $k=>$v) {
                        $ret[\lcfirst($k)] = $v;
                    }
                    $ret['author'] = (string) $val->tail()->id();
                    
                    return [$val->id()->toString() => $ret];
                }, 
                $page->getComments()
         );
         $this->succeed(
             $response, [
                "comments"=>$comments
             ]
         );
    }

    public function delComment(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['comment_id']);
        if(!$v->validate()) {
            $this->fail($response, "Comment_id field is required.");
            return;
        }
        $i = $kernel->gs()->node($id);  
        if(!$i->hasComment(ID::fromString($data["comment_id"]))) {
            $this->fail($response, "Comment_id does not belong to you.");
            return;
        }
        $comment = $kernel->gs()->edge($data["comment_id"]);
        $comment->destroy();
        $this->succeed($response);
    }
 
    public function unstar(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['url']);
        $v->rule('url', ['url']);
        if(!$v->validate()) {
            $this->fail($response, "Url required.");
            return;
        }
        $i = $kernel->gs()->node($id);  
        $page = $this->_fromUrlToNode($kernel, $data["url"]);
        $stars = iterator_to_array($i->edges()->between($page->id(), Star::class));
        error_log("Total star count: ".count($stars));
        foreach($stars as $star) {
            error_log("Star ID: ".$star->id()->toString());
            $star->destroy();
        }
        $this->succeed($response);
    }
 
    /**
     * Fetch starred content
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function fetchStarredContent(Request $request, Response $response, Kernel $kernel)
    {
        $res = $kernel->index()->client()->run("MATCH ()-[e:star]-(n:page) WITH n.Url AS content, n.Title AS the_title, count(e) AS star_count RETURN the_title, content, star_count ORDER BY star_count");
        //$res = $kernel->index()->client()->run("MATCH ()-[e:star]-(n:page) WITH n.Url AS content, count(e) AS star_count RETURN content, star_count ORDER BY star_count");
        //eval(\Psy\sh());
        $array = $res->records();
        $ret = [];
        foreach($array as $a) {
            //$ret[$a->value("content")] = $a->value("star_count");
            $ret[$a->value("content")] = [
                "title" => $a->value("the_title"), 
                "star_count" => $a->value("star_count")
            ];
        }
        if(count($array)==0) {
            $this->fail($response, "No content starred yet");
        } 
        $this->succeed($response, ["pages"=>$ret]);
    }

    public function fetchMyStars(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $res = $kernel->index()->client()->run(
            "MATCH (:user {udid: {me}})-[e:star]-(n:page) WITH n.Url AS content, n.Title AS the_title, count(e) AS star_count RETURN the_title, content, star_count ORDER BY star_count", 
            array("me"=>$id)
        );
        //$res = $kernel->index()->client()->run("MATCH ()-[e:star]-(n:page) WITH n.Url AS content, count(e) AS star_count RETURN content, star_count ORDER BY star_count");
        //eval(\Psy\sh());
        $array = $res->records();
        $ret = [];
        foreach($array as $a) {
            //$ret[$a->value("content")] = $a->value("star_count");
            $ret[$a->value("content")] = [
                "title" => $a->value("the_title"), 
                "star_count" => $a->value("star_count")
            ];
        }
        if(count($array)==0) {
            $this->fail($response, "No content starred yet");
        } 
        $this->succeed($response, ["pages"=>$ret]);
    }

}
