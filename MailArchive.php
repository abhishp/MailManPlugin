<?php
include 'ganon.php';
include 'EMail.php';

class MailArchive {
    private $listName;
    private $adminEmail;
    private $adminPW;
    private $main_url;
    private $mails;
    private $mailMonths;

    function __construct($name, $password, $email, $main_url) {
        $this->listName = $name;
        $this->adminPW = $password;
        $this->adminEmail = $email;
        $this->main_url = $main_url;
        $this->mails = null;
        $this->mailMonths = $this->getAvailableMonths();
    }

    private function simplePostRequest($url) {
        $request = new HttpRequest($url,HttpRequest::METH_POST);
        $request->addPostFields(array(
            'username' => $this->adminEmail,
            'password' => $this->adminPW,
        ));
        return $request->send()->getBody();
    }

    //To get content of mail or an archive.
    private function getSubjectContent($year,$month,$subject){
        $url = "{$this->main_url}/{$this->listName}/{$year}-{$month}/{$subject}.html";
        return $this->simplePostRequest($url);
    }

    private function getAvailableMonths() {
        $url = "{$this->main_url}/{$this->listName}";
        $content = $this->simplePostRequest($url);
        preg_match_all('/<td>(January|February|March|April|May|June|July|August|September|October|November|December)\s(\d{4}):<\/td>/', $content, $months);

        $availableMonths = array();

        foreach ($months[1] as $key => $month) {
            if(array_key_exists($months[2][$key], $availableMonths)) {
                $availableMonths[$months[2][$key]][date_parse($month)['month']] = $month;
            } else {
                $availableMonths[$months[2][$key]] = array(date_parse($month)['month']=>$month);
            }
        }

        return $availableMonths;
    }

    public function printAvailableMonths() {
        foreach ($this->mailMonths as $year => $months) {
            echo "{$year}\n";
            foreach ($months as $key => $month) {
                echo "\t{$month}\n";
            }
        }
    }

    private function parseEmail($mailID, $email) {
        preg_match('/<H1>(.+)<\/H1>/i', $email, $title);
        $title = $title[1];
        preg_match('/<B>(.+)<\/B>/i', $email,$author);
        $author = $author[1];
        preg_match('/<a href="mailto.*?>(.+?)<\/a>/is', $email,$authorEmailID);
        $authorEmailID = trim(str_replace(" at ","@",$authorEmailID[1]));
        preg_match('/<PRE>(.+)<\/PRE>/is', $email, $body);
        $body = $body[1];
        preg_match('/<I>(.+)<\/I>/i', $email, $date_sent);
        $date_sent = $date_sent[1];

        return new Email($mailID, $title, $author, $authorEmailID ,$body,$date_sent);
    }

    public function parseMonthlyArchiveByThread($month, $year) {
        $content = $this->getSubjectContent($year,$month,'thread');
        $content = preg_replace("/[\n\t\r]+/",'',$content);
        $dom = str_get_dom($content);
        $mails = $dom('body > ul:nth-of-type(1)');
        $threadedMails = $this->makeThread($mails[0]);
        var_dump($threadedMails);
        return $threadedMails;
    }

    private function makeThread($mails,&$parsedMails=null) {
        if(!$parsedMails) {
           $parsedMails = array();
        }
        foreach($mails->children as $key => $mail) {
            if( $mail->isTextOrComment()) {
                continue;
            }
            $mailLink = $mail('a:first-child')[0];
            $mailID = rtrim($mailLink->href,'.html');

            if($mail('ul')) {
                $parsedMails[$mailID] = array('0'=>$this->mails[$mailID]);
                $this->makeThread($mail('ul')[0],$parsedMails[$mailID]);
            } else {
                $parsedMails[$mailID] = &$this->mails[$mailID];
            }
        }
        return $parsedMails;
    }

    public function getAllMails($startMonth='January', $startYear='0') {
        if(!$this->mails) {
            foreach ($this->mailMonths as $year => $months) {
                if($year < $startYear) {
                    continue;
                } elseif ($year > $startYear) {
                    if(!$this->mails)
                        $this->mails = array();
                    foreach ($months as $key => $month) {
                        $this->mails = array_merge($this->mails,
                            $this->parseMonthlyArchive($month, $year));
                    }
                } else {
                    if(!$this->mails)
                        $this->mails = array();
                    foreach ($months as $key => $month) {
                        if($key < date_parse($startMonth)['month']) {
                            continue;
                        }
                        $this->mails = array_merge($this->mails,
                            $this->parseMonthlyArchive($month, $year));
                    }
                }
            }
        }
        return $this->mails;
    }

    public function parseMonthlyArchive($month, $year, $archiveType = 'date') {
        $content = $this->getSubjectContent($year,$month, $archiveType);
        preg_match_all('/(?:([0-9]{6})\.html)/', $content,$matches);
        $mails = array();
        foreach ( $matches[1] as $key => $mailID ) {
            $content = $this->getSubjectContent($year,$month,$mailID);
            $mail = $this->parseEmail($mailID,$content);
            $mails[$mailID] = $mail;
        }
        return $mails;
    }

    public function printThread($mailList=null, $indent=''){
        if($mailList==null)
            $mailList=$this->mails;
        foreach($mailList as $mailUIN => $mail) {
            echo $indent.$mail->getSubject();
            if($mail->hasChild()) {
                $childMails = $mail->getChildren();
                $this->printThread($childMails,$indent."\t");
            }
        }
    }
}
