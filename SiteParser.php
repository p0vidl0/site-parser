<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

class SiteParser
{
    private $html;
    private $wpClient;
    
    private $params = [
        'moreTagPosition' => 2,
    ];
    
    function __construct($params) {
        $this->params = array_merge_recursive($this->params,$params);
    }
    
    function putNewPosts()
    {
        $startTime = time();

        $urls = explode("\n", file_get_contents($this->params['list']));
        echo $urls[0] . "\n";
        $this->getPost($urls[0]);

        $timeUsed = time() - $startTime;
        $memDivider = 1024*1024;

        echo "memory used: " . round(memory_get_usage()/($memDivider),0) . "Mb (" . round(memory_get_usage(true)/($memDivider),0) ."Mb)\n";
        echo "time used: " . $timeUsed . " sec.\n";
    }
    
    function rusStr2Date($strDate,$dateTime)
    {
        $arr = explode(' ', $strDate);
        switch (mb_strtolower(mb_substr($arr[1],0,3))) {
            case 'янв':
                $month = 1;
                break;
            case 'фев':
                $month = 2;
                break;
            case 'мар':
                $month = 3;
                break;
            case 'апр':
                $month = 4;
                break;
            case 'мая':
            case 'май':
                $month = 5;
                break;
            case 'июн':
                $month = 6;
                break;
            case 'июл':
                $month = 7;
                break;
            case 'авг':
                $month = 8;
                break;
            case 'сен':
                $month = 9;
                break;
            case 'окт':
                $month = 10;
                break;
            case 'ноя':
                $month = 11;
                break;
            case 'дек':
                $month = 12;
                break;
            default:
                return false;
        }
        $dateTime->setDate($arr[2], $month, $arr[0]);
        $dateTime->setTime(mt_rand(0, 23), mt_rand(0, 59), mt_rand(0, 59));
        echo $strDate . " : " .$dateTime->format("d.m.Y") . "\n";
        return $dateTime;
    }
    
    function getPostHeader()
    {
        $postHeader = '';
        $header = $this->html->find($this->params['header']);
        if (count($header))
        {
            $postHeader = $header[0]->innertext;    
            echo "Article header: '$postHeader'\n";
            $header[0]->outertext = "";
            $this->html->load($this->html->save());
        } else
        {
            echo "Can't find heared\n";
        }
        return $postHeader;
    }
    
    function getPostDate()
    {
        if (isset($this->params['postDate']))
        {            
            if (isset($this->params['postDate']['function']))
            {
                $postDate = eval($this->params['postDate']['function']);
            }
            return $postDate;
        }
        return false;
    }
    
    function getTags()
    {
        $postTags = [];
        $tags = $this->html->find($this->params['tags']);
        
        if (count($tags))
        {   
            foreach ($tags as $tag)
            {
                array_push($postTags,$tag->innertext);
            }
            return $postTags;
        }
        return false;
    }
    
    function getCategory()
    {
        $category = $this->html->find($this->params['category'],0);
        
        if ($category)
        {   
            return $category->innertext;
        }
        return false;
    }
    
    function getDatetime()
    {
        $datetime = $this->html->find($this->params['datetime'],0);
        
        if ($datetime)
        {   
            return DateTime::createFromFormat('d.m.Y H:i:s', mb_substr($datetime->innertext,0,10) . ' ' . mt_rand(0, 23) . ':' . mt_rand(0, 59) . ':' . mt_rand(0, 59));
        }
        return false;
    }
    
    function getComments()
    {
        $comments = [];
        $cmnts = $this->html->find($this->params['comments']['selector']);
        if (count($cmnts))
        {
            foreach ($cmnts as $cmnt)
            {
                $comment = [];
                $comment['author'] = $cmnt->find($this->params['comments']['author']['selector'],$this->params['comments']['author']['position'])->innertext;
                
                $datetime = $cmnt->find('p.commenticon',0)->innertext;
                $datetime = mb_substr($datetime,mb_strrpos($datetime,'</strong>')+10);
                $date = mb_substr($datetime,0,  mb_strpos($datetime,','));               
                $comment['datetime'] = new DateTime();
                $comment['datetime'] = $this->rusStr2Date($date, $comment['datetime']);
                $comment['datetime']->setTime(mb_substr($datetime,-5,2), mb_substr($datetime,-2,2), mt_rand(0, 59)); 
                $ps = $cmnt->find('p');
                if (count($ps > 1))
                {      
                    $comment['content'] = '';
                    array_shift($ps);
                    
                    foreach ($ps as $p)
                    {
                        $comment['content'] .= $p->outertext;
                    }
                    array_push($comments,$comment);
                }                
            }
            return $comments;
        }
        return false;
    }
    
    function preDeleteBlocks()
    {
        if (isset($this->params['postDeleteBlocks']))
        {
            foreach($this->params['postDeleteBlocks'] as $delBlock)
            {
                $del = $this->html->find("article.content",0)->find($delBlock);
                if (count($del))
                {
                    foreach ($del as $d)
                    {
                        $d->outertext = "";
                    }
                }
            }
        }
        $this->html->load($this->html->save());
    }
    
    function convertPostImages($post)
    {
        $imgs = $post->find('img');
        if (count($imgs))
        {            
            if (!isset($this->wpClient))
            {
                $this->wpClient = $this->wpClientInit();
            }
            foreach ($imgs as $img)
            {
                $alt = $img->alt;
                $url = trim($img->src);
                if ($url[0]=='/')
                {
                    
                    $url = $this->params['donorUrl'] . substr($url,1);
                }
                $width = $img->width;
                $height = $img->height;
                $imageName = substr($url,  strrpos($url, '/') + 1); 
                $res = $this->wpClient->uploadFile($imageName, 'image/jpeg', file_get_contents($url));
                $img->outertext = '<img src="' . $res['url'] . '"'
                        . ($alt != '' ? " alt=\"$alt\"":'')
                        . ($width != '' ? " width=\"$width\"" : '')
                        . ($height != '' ? " height=\"$height\"" : '')
                   . '>';
            }
        }
        return $post;
    }
    
    function convertObjects($content)
    {
        $objects = $content->find('object');
        if (count($objects))
        {            
            if (!isset($this->wpClient))
            {
                $this->wpClient = $this->wpClientInit();
            }
            foreach ($objects as $object)
            {
                $objectData = $object->data;
                if ($objectData[0]=='/')
                {
                    
                    $objectData = $this->params['donorUrl'] . substr($objectData,1);
                }
                $objectFileName = substr($objectData,  strrpos($objectData, '/') + 1); 
                $res = $this->wpClient->uploadFile($objectFileName, 'image/jpeg', file_get_contents($objectData));      
                $object->data = $res['url'];
                $object->find('param[name=movie]',0)->value = $res['url'];
            }
        }
        return $content;
        
    }
    
    function convertHrefs($content)
    {
        $as = $content->find('a');
        if (count($as))
        {
            foreach ($as as $a)
            {
                $a->href = substr($a->href,strrpos($a->href,'/'));
            }
        }
        return $content;
    }
    
    function getPostContent()
    {        
        $entry = $this->html->find($this->params['postContent'],0);
        $entry = $this->convertPostImages($entry);       
        $entry = $this->convertHrefs($entry);
        foreach ($entry->find('p[style=text-align: center;]') as $r)
        {
            $r->outertext = '';
        }
        $paragraphs = $entry->find('p');
        if (count($paragraphs) > $this->params['moreTagPosition'])
        {
            $paragraphs[$this->params['moreTagPosition'] - 1]->outertext .= "<!--more-->";
        }
        $content = $entry->innertext;
        if (isset($this->params['postCutAfter']))
        {
            $content = mb_substr($content,0,mb_strpos($content,  $this->params['postCutAfter']));
        }
        return $content;
    }
    
    function getMetaDescription()
    {
        $metaDescription = $this->html->find('meta[name=description]',0);
        if ($metaDescription)
        {
            return $metaDescription->content;
        }
        $entry = $this->html->find($this->params['postContent'],0);
        $metaDescription = trim(mb_substr($entry->plaintext,0,156));
        return $metaDescription;
    }

    function getPost($donorPostURL)
    { 
        $this->html = file_get_html($donorPostURL);        
        $post['datetime'] = $this->getDateTime();
        $dt = $post['datetime'];
        $this->changeSystemTime($dt);
        $post['comments'] = $this->getComments();
        $post['postHeader'] = $this->getPostHeader();
//        $this->preDeleteBlocks();
        $post['postContent'] = $this->getPostContent();
        $post['metaDescription'] = $this->getMetaDescription();
        $post['tags'] = $this->getTags();
        $post['category'] = $this->getCategory();
        
        return $post;        
    }
    
    function wpClientInit($anonymous=false)
    {       
        $endpoint = $this->params['recipientUrl'] . "xmlrpc.php";
        $wpClient = new \HieuLe\WordpressXmlrpcClient\WordpressClient(null,null,null,null);
        if ($anonymous)
        {
            $wpClient->setCredentials($endpoint,null,null);
        } else
        {
            $wpClient->setCredentials($endpoint, $this->params['wpLogin'], $this->params['wpPassword']);
        }
        return $wpClient;
    }
    
    function putPost($post)
    {
        if (!isset($this->wpClient))
        {
            $this->wpClient = $this->wpClientInit();
        }
        $newPostId = $this->wpClient->newPost($post['postHeader'], $post['postContent'],[
            'post_date' => DateTime::createFromFormat('d.m.Y H:i:s', '20.02.2013 12:21:00'),
            'comment_status' => 'open',
            'terms_names'=> [
                'post_tag' => $post['tags'],
                'category' => [
                    $post['category'],
                ],
            ],
            'custom_fields' => [
                [
                    'key' => '_yoast_wpseo_metadesc',
                    'value' => $post['metaDescription'],
                ],
                [
                    'key' => '_yoast_wpseo_focuskw',
                    'value' => is_array($post['tags'])?implode(" ",$post['tags']):$post['postHeader'],
                ]
            ]
        ]);
        if ($newPostId)
        {
            $this->wpClient->editPost($newPostId,[]);
            if (count($post['comments']))
            {
                $wpCommentsClient = $this->wpClientInit(true);
                foreach ($post['comments'] as $comment)
                {
                    $newComment = $wpCommentsClient->newComment($newPostId,[
                        'content' => $comment['content'],
                        'author' => $comment['author'],
                    ]);
                    if ($newComment)
                    {
                        $this->wpClient->editComment($newComment, [
                            'date_created_gmt' => $comment['datetime'],
                        ]);
                    }
//                    if (isset($comment['subComments']))
//                    {
//                        foreach ($comment['subComments'] as $subComment)
//                        {
//                            $newComment = $wpCommentsClient->newComment($newPostId,[
//                                'comment_parent' => $newComment,
//                                'content' => $subComment['content'],
//                                'author' => $subComment['author'],
//                            ]);                            
//                            if ($newComment)
//                            {
//                                $this->wpClient->editComment($newComment, [
//                                    'date_created_gmt' => $comment['datetime'],
//                                ]);
//                            }
//                        }
//                    }
                }
            }            
            $newPost = $this->wpClient->getPost($newPostId);
            return $newPost['link'];
        }
        return false;
    }
    
    function rePost($donorPostURL)
    {
        $post = $this->getPost($donorPostURL);
        $this->putPost($post);
    }
    
    function batchRePost()
    {
        $urls = explode("\n", file_get_contents($this->params['list']));
        $i = 0;
        
        foreach($urls as $url)
        {
            echo $i++ ."_";
            $this->rePost($url);           
        }
    }
    
    function changeSystemTime($datetime)
    {
//        var_dump($datetime);
//        $command = 'date +%Y%m%d -s' . $datetime->format('Ymd');
//        shell_exec($command);
    }
}