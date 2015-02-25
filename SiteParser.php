<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require 'vendor\autoload.php';

class SiteParser
{
    private $html;
    private $wpClient;
    
    private $params = [
        'postDeleteBlocks' => [
            'div.google_2',
            'div.prev_next_links',
            'script',
            '#hd_cm',
            'div.tabla',
            'span.mh3',
            'form.formsp'
        ],
        'moreTagPosition' => 2,
    ];
    
    function __construct($params) {
        $this->params = array_merge_recursive($this->params,$params);
//        var_dump($this->params);
//        die();
    }
    
    function putNewPosts()
    {
        $startTime = time();

        $urls = explode("\n", file_get_contents($this->params['list']));
//        $i = 0;
//        foreach($urls as $url)
//        {
//            echo $i++ .": $url\n";
//            getDonorPost($url);
//        }
        echo $urls[0] . "\n";
        $this->getPost($urls[0]);

        $timeUsed = time() - $startTime;
        $memDivider = 1024*1024;

        echo "memory used: " . round(memory_get_usage()/($memDivider),0) . "Mb (" . round(memory_get_usage(true)/($memDivider),0) ."Mb)\n";
        echo "time used: " . $timeUsed . " sec.\n";
    }
    
    function rusStr2Time($strDate,$dateTime)
    {
        $arr = explode(' ', $strDate);
        switch (mb_substr($arr[1],0,3)) {
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
        return $dateTime;
    }
    
    function getPostHeader()
    {
        $postHeader = '';
        $header = $this->html->find('article.content h1');
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
        $publicDate = $header->next_sibling()->find('p',0);
        date_default_timezone_set("Europe/Moscow");
        $postDate = new DateTime();
        $this->rusStr2Time($publicDate->innertext,$postDate);
        return $postDate;
    }
    
    function getTags()
    {
        $ems = $this->html->find('article.content em');
        $emsCount = count($ems);
        if ($emsCount > 1)
        {
            $em = $ems[$emsCount-1];

            $tagsContainer = $em->find('a');
            if (count($tagsContainer))
            {   
                foreach ($tagsContainer as $tagContainer)
                {
                    array_push($postTags,$tagContainer->innertext);
                }
            }
        }
        return $postTags;
    }
    
    function getComments()
    {
        $comments = [];
        $cmnts = $this->html->find('div.tabla');
        if (count($cmnts))
        {
            for ($i = 0; $i < count($cmnts); $i++)
            {
                $comments[$i]['author'] = $cmnts[$i]->find('div.subjblg',0)->plaintext;
                $comments[$i]['content'] = $cmnts[$i]->find('div.comtblg',0)->innertext;
                
                date_default_timezone_set("Europe/Moscow");
                $comments[$i]['datetime'] = DateTime::createFromFormat('d.m.Y H:i:s', mb_substr($cmnts[$i]->find('div.fftimeblg',0)->innertext,13));
//                echo "Comment #$i: by '" . $comments[$i]['author'] . "' '" . $comments[$i]['datetime'] ."\n\n";
                $cmntsLevel2 = $cmnts[$i]->find('div.tablardisplay div.commain');
//                echo count($cmntsLevel2) . "\n";
                if (count($cmntsLevel2))
                {
                    for ($j = 0; $j < count($cmntsLevel2);$j++)
                    {
                        $comments[$i]['subComments'][$j]['author'] = $cmntsLevel2[$j]->find('div.subjblg',0)->plaintext;
                        $comments[$i]['subComments'][$j]['content'] = $cmntsLevel2[$j]->find('div.comtblg',0)->innertext;
                        $comments[$i]['subComments'][$j]['datetime'] = DateTime::createFromFormat('d.m.Y H:i:s', mb_substr($cmntsLevel2[$j]->find('div.fftimeblg',0)->innertext,13));
//                        echo "---Sub comment #$j: by '" . $comments[$i]['subComments'][$j]['author'] . "' '" . $comments[$i]['subComments'][$j]['content'] ."\n\n";
                    }
                }
            }
//            die();
        }
        return $comments;
    }
    
    function preDeleteBlocks()
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
//                    $url = str_replace('//','/',$url);
                }
                $width = $img->width;
                $height = $img->height;
                $imageName = substr($url,  strrpos($url, '/') + 1); 
//                echo "Try save '$url' to '$imageName'.\n";
                $res = $this->wpClient->uploadFile($imageName, 'image/jpeg', file_get_contents($url));
                $img->outertext = '<img src="' . $res['url'] . '"'
                        . ($alt != '' ? " alt=\"$alt\"":'')
//                       . is_array($postTags) && count($postTags) ? ' title="' . implode(' ',$postTags) . '"' : '' 
                        . ($width != '' ? " width=\"$width\"" : '')
                        . ($height != '' ? " height=\"$height\"" : '')
                   . '>';
//                echo $img->outertext . "\n";
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
//                    $url = str_replace('//','/',$url);
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
        $entry = $this->html->find('article.content',0);
//        $postContent = mb_substr($entry->innertext,0,mb_strpos($entry->innertext,'<!-- tags begin -->'));
        $entry = $this->convertPostImages($entry);       
        $entry = $this->convertHrefs($entry);
        $entry = $this->convertObjects($entry);
        $paragraphs = $entry->find('p');
        if (count($paragraphs) > $this->params['moreTagPosition'])
        {
            $paragraphs[$this->params['moreTagPosition'] - 1]->outertext .= "<!--more-->";
        }
        return $entry->innertext;
    }
    
    function getMetaDescription()
    {
        $entry = $this->html->find('article.content',0);
        $metaDescription = mb_substr($entry->plaintext,0,156);
        return $metaDescription;
    }

    function getPost($donorPostURL)
    {
        $this->html = file_get_html($donorPostURL);
        
        $post['comments'] = $this->getComments();
        $post['postHeader'] = $this->getPostHeader();
        $this->preDeleteBlocks();
        $post['postContent'] = $this->getPostContent();
        $post['metaDescription'] = $this->getMetaDescription();
        
        return $post;        
    }
    
    function wpClientInit($anonymous=false)
    {       
        $endpoint = $this->params['recipientUrl'] . "xmlrpc.php";
        $wpClient = new \HieuLe\WordpressXmlrpcClient\WordpressClient();
        if ($anonymous)
        {
            $wpClient->setCredentials($endpoint,'','');
        } else
        {
            $wpClient->setCredentials($endpoint, $this->params['wpLogin'], $this->params['wpPassword']);
        }
        return $wpClient;
    }
    
    function putPost($post)
    {
        
        date_default_timezone_set("Europe/Moscow");
//        $fileName = date("Y-m-d-H-i-s") . "-res.html";
//        file_put_contents($fileName,$postContent);
        if (!isset($this->wpClient))
        {
            $this->wpClient = $this->wpClientInit();
        }
        $newPostId = $this->wpClient->newPost($post['postHeader'], $post['postContent'],[
            'post_date' => DateTime::createFromFormat('d.m.Y H:i:s', '20.02.2013 12:21:00'),
            'comment_status' => 'open',
            'terms_names'=> [
//                'post_tag' => $postTags,
                'category' => [
                    'Уроки ActionScript 3.0',
                ],
            ],
            'custom_fields' => [
                [
                    'key' => '_yoast_wpseo_metadesc',
                    'value' => $post['metaDescription'],
                ],
//                [
//                    'key' => '_yoast_wpseo_focuskw',
//                    'value' => implode(" ",$postTags),
//                ]
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
                    if (isset($comment['subComments']))
                    {
                        foreach ($comment['subComments'] as $subComment)
                        {
                            $newComment = $wpCommentsClient->newComment($newPostId,[
                                'comment_parent' => $newComment,
                                'content' => $subComment['content'],
                                'author' => $subComment['author'],
                            ]);                            
                            if ($newComment)
                            {
                                $this->wpClient->editComment($newComment, [
                                    'date_created_gmt' => $comment['datetime'],
                                ]);
                            }
                        }
                    }
                }
            }
        }
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
}