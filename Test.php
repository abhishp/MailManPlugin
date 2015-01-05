<?php
// include 'simple_html_dom.php';
// require_once 'html5/bin/html5-parse.php';
include 'ganon.php';

//POST-Data for a list subcription

class SubscriberList {
    private $name;
    private $password;
    private $email;
    private $main_url;
    private $encoding;

    private static $SUBSCRIBE_DATA = array(
        "subscribe_or_invite" => 0,
        "send_welcome_msg_to_this_batch" => 0,
        "notification_to_list_owner" => 0 ,
        "adminpw" => NULL,
        "subscribees_upload" => NULL,
    );

    private static $UNSUBSCRIBE_DATA = array(
        "send_unsub_ack_to_this_batch" => 0,
        "send_unsub_notifications_to_list_owner" => 0,
        "adminpw" => NULL,
        "unsubscribees_upload" => NULL,
    );

    private static $SUBSCRIBE_MSG = array(
        "Successfully subscribed",
    );

    private static $UNSUBSCRIBE_MSG = array(
        'Successfully Removed',
        "Successfully Unsubscribed",
    );

    private static $NON_MEMBER_MSG = array(
        "Cannot unsubscribe non-members"
    );

    private static $UNSUBSCRIBE_BUTTON = array(
        'en' => "Unsubscribe",
        'fr' => "Résilier",
    );


    function __construct($name, $password, $email, $main_url, $encoding) {
        $this->name = $name;
        $this->password = $password;
        $this->email = $email;
        $this->main_url = $main_url;
        $this->encoding = $encoding;
    }

    private function parse_status_content($content) {
        if(!$content)
            throw new Exception("Invalid content!", 1);

        preg_match('|(?<=<h5>).+(?=:[ ]{0,1}</h5>)|', $content, $m);
        //preg_match('/(?:<h5>)[^:]+(?::? *<\/h5>)/', $content, $m);
        if($m) {
            $msg = rtrim($m[0]);
        } else {

            preg_match('|(?<=<h3><strong><font color="#ff0000" size="\+2">).+(?=:[ ]{0,1}</font></strong></h3>)|', $content, $m);
            //preg_match("/(?:<h3><strong><font color='#ff0000' size='\+2'>)".
            //             "[^:]+(?::? *<\/font><\/strong><\/h3>)/", $content, $m);

            if ($m) {
                $msg = $m[0];
            } else {
                throw new Exception("Could not find status message",1);
            }
        }

        //preg_match("/(?:<ul>\n*<li>\n*).+(?:\n*<\/li>\n*<\/ul>\n*)/", $content, $m);
        preg_match('|(?<=<ul>\n<li>).+(?=\n</ul>\n)|', $content, $m);
        if ($m) {
            $member = $m[0];
        } else {
            throw new Exception("Could not find member-information",1);
        }

        return array('msg' => $msg, 'member' => $member);
    }

    private function parse_member_content($content) {
        if (!$content){
            throw new Exception("No valid Content!");
        }

        $members = [];
        preg_match_all("/letter=\w{1}/", $content, $letters);
        preg_match_all("/chunk=\d+/", $content, $chunks);
        preg_match_all('|name=".+_realname" type="TEXT" value=".*" size="[0-9]+" >|', $content, $input);
        foreach ($input[0] as $key => $member) {
            $info = preg_split('/" /',$member);
            preg_match('/(?<=name=").+(?=_realname)/', $info[0],$email);
            $email = $email[0];
            preg_match('/(?<=value=").*/', $info[2], $realname);
            $realname = $realname[0];
            $members[] = array('realname' => $realname, 'email' => $email);
        }
        return array('letters' => array_unique($letters[0]), 'members' => $members, 'chunks' => $chunks[0]);
    }

    private function get_admin_moderation_url() {
        return "{$this->main_url}/admindb/{$this->name}/?adminpw={$this->password}";
    }

    private function formatEmailAddress($name, $email) {
        $name = trim($name);

        if($name == '') {
            return $email;
        }
        return "{$name} <{$email}>";
    }

    public function subscribe($email, $first_name='', $last_name='') {

        $url = "{$this->main_url}/admin/{$this->name}/members/add";

        $name = "{$first_name} {$last_name}";

        self::$SUBSCRIBE_DATA['adminpw'] = $this->password;
        self::$SUBSCRIBE_DATA['subscribees_upload'] = $this->formatEmailAddress(trim($name),$email);

        $request = new HttpRequest($url, HttpRequest::METH_POST);
        $request->addPostFields(self::$SUBSCRIBE_DATA);
        $content = $request->send()->getBody();
        //opener = urllib2.build_opener(MultipartPostHandler(self.encoding, True))
        //content = opener.open(url, SUBSCRIBE_DATA).read()
        $mesage = $this->parse_status_content($content);

        if (!in_array($mesage['msg'],self::$SUBSCRIBE_MSG)) {
            $error = "{$mesage['msg']}: {$mesage['member']}";
            throw new Exception($error);
        }

        return "{$mesage['msg']}: {$mesage['member']}";
    }

    public function unsubscribe($email) {
        $url = "{$this->main_url}/admin/{$this->name}/members/remove";
        self::$UNSUBSCRIBE_DATA['adminpw'] = $this->password;
        self::$UNSUBSCRIBE_DATA['unsubscribees_upload'] = $email;
        $request = new HttpRequest($url, HttpRequest::METH_POST);
        $request->addPostFields(self::$UNSUBSCRIBE_DATA);
        $content = $request->send()->getBody();

        $message = $this->parse_status_content($content);
        if (!in_array($message['msg'], self::$UNSUBSCRIBE_MSG) && !in_array($message['msg'], self::$NON_MEMBER_MSG)) {
            $error = "{$message['msg']}: {$message['member']}";
            throw new Exception($error);
        }

        return "{$message['msg']}: {$message['member']}";
    }

    public function get_all_members() {
        $url = "{$this->main_url}/admin/{$this->name}/members/list";
        $data = array( 'adminpw' => $this->password );
        $request = new HttpRequest($url, HttpRequest::METH_POST);
        //opener = urllib2.build_opener(MultipartPostHandler(self.encoding))

        $all_members = [];
        try {
            $request->addPostFields($data);
            $content = $request->send()->getBody();
            //    content = opener.open(url, data).read()
        } catch (Exception $e) {
            print_r($e);
            return false;
        }
        $result = $this->parse_member_content($content);
        foreach ($result['letters'] as $key => $letter) {
            $url_letter = "{$url}?{$letter}";
            $request = new HttpRequest($url_letter,HttpRequest::METH_POST);
            $request->addPostFields($data);
            $content = $request->send()->getBody();
            //$content = opener.open(url_letter, data).read()
            $temp_result = $this->parse_member_content($content);
            $all_members = array_merge($all_members, $temp_result['members']);
            array_shift($temp_result['chunks']);
            foreach ($temp_result['chunks'] as $key => $chunk) {
                $url_letter_chunk = "{$url}?{$letter}&{$chunk}";
                $request = new HttpRequest($url_letter_chunk,HttpRequest::METH_POST);
                $request->addPostFields($data);
                $content = $request->send()->getBody();
                $letter_chunk_result = $this->parse_member_content($content);
                $all_members = array_merge($all_members, $letter_chunk_result['members']);
            }
        }

        $members = array();
        foreach ($all_members as $key => $member) {
            $email = str_replace("%40", "@", $member['email']);
            $members[$email] = $member['realname'];
        }
        return $members;
    }

    public function user_subscribe($email, $password, $language='en', $first_name='', $last_name='') {

        $url = "{$this->main_url}/subscribe/{$this->name}";

        // password = check_encoding(password, self.encoding)
        // email = check_encoding(email, self.encoding)
        // first_name = check_encoding(first_name, self.encoding)
        // last_name = check_encoding(last_name, self.encoding)
        $name = "{$first_name} {$last_name}";

        self::$SUBSCRIBE_DATA['email'] = $email;
        self::$SUBSCRIBE_DATA['pw'] = $password;
        self::$SUBSCRIBE_DATA['pw-conf'] = $password;
        self::$SUBSCRIBE_DATA['fullname'] = $name;
        self::$SUBSCRIBE_DATA['language'] = $language;
        $request = new HttpRequest($url, HttpRequest::METH_POST);
        $request->addPostFields(self::$SUBSCRIBE_DATA);
        $content = $request->send()->getBody();
        foreach(self::$SUBSCRIBE_MSG as $key => $status) {
            preg_match_all($status, $content, $matches);
            if (count($matches[0]) > 0 ) {
                return true;
            }
        }
        throw new Exception($content);
    }

    public function user_unsubscribe($email, $language='en') {
        $url = "{$this->main_url}/options/{$this->name}/{$email}";

        $UNSUBSCRIBE_DATA['email'] = $email;
        $UNSUBSCRIBE_DATA['language'] = $language;
        $UNSUBSCRIBE_DATA['login-unsub'] = self::$UNSUBSCRIBE_BUTTON[$language];

        $request = new HttpRequest($url, HttpRequest::METH_POST);
        $request->addPostFields(self::$UNSUBSCRIBE_DATA);
        $content = $request->send()->getBody();
        # no error code to process
    }
}

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

    //To get the exact body from a raw mail body (exclude the threaded mail)
    private function getBodyFromRawBody($body) {
        $lines = explode("\n", $body);
        $body = array();
        foreach ($lines as $key => $line) {
            if( preg_match("/^.*&gt;.+?$/", $line) == 0) {
                $body[] = $line;
            }
        }
        $body = implode("\n",$body);
        return $body;
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
        // $body = preg_replace('|(&gt;)+?<i>(.*?)</i>|is', '', $body);
        // $body = $this->getBodyFromRawBody($body);
        preg_match('/<I>(.+)<\/I>/i', $email, $date_sent);
        $date_sent = $date_sent[1];

        return new Email($mailID, $title, $author, $authorEmailID ,$body,$date_sent);
    }

    public function parseMonthlyArchiveByThread($month,$year) {
        $content = $this->getSubjectContent($year,$month,'thread');
        $content = preg_replace("/[\n\t\r]+/",'',$content);
        $dom = str_get_dom($content);
        $mails = $dom('body > ul:nth-of-type(1) > li');
        $threadedMails = $this->makeThread($mails);
        var_dump($threadedMails);
    }

    private function makeThread($mails,&$parsedMails=null) {
        if(!$parsedMails) {
           $parsedMails = array();
        }
        foreach($mails as $key => $mail) {
            $mailLink = $mail('a:first-child')[0];
            $mailID = rtrim($mailLink->href,'.html');

            if($mail('ul')) {
                $parsedMails[$mailID] = array('0'=>$mailLink->getPlainText());
                $this->makeThread($mail('ul:nth-of-type(0) > li'),$parsedMails[$mailID]);
            } else {
                $parsedMails[$mailID] = $mailLink->getPlainText();
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
                            $this->parseMonthlyArchiveByDate($month, $year));
                    }
                } else {
                    if(!$this->mails)
                        $this->mails = array();
                    foreach ($months as $key => $month) {
                        if($key < date_parse($startMonth)['month']) {
                            continue;
                        }
                        $this->mails = array_merge($this->mails,
                            $this->parseMonthlyArchiveByDate($month, $year));
                    }
                }
            }
        }
        return $this->mails;
    }

    public function parseMonthlyArchiveByDate($month,$year) {
        $content = $this->getSubjectContent($year,$month,'date');
        preg_match_all('/(?:([0-9]{6})\.html)/', $content,$matches);
        $mails = array();
        foreach ( $matches[1] as $key => $mailID ) {
            $content = $this->getSubjectContent($year,$month,$mailID);
            $mail = $this->parseEmail($mailID,$content);
            $mails[$mailID] = $mail;
            //$mail->printMail();
            // // echo $content;
            //echo "\n\n";
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

class EMail {
    private $subject;
    private $senderName;
    private $senderMailID;
    private $body;
    private $date_sent;
    private $mailUIN;
    private $threadChild;

    function __construct($mailID, $subject, $senderName, $senderMailID, $body, $date_sent) {
        $this->mailUIN = $mailID;
        $this->subject = $subject;
        $this->senderName = $senderName;
        $this->senderMailID = $senderMailID;
        $this->body = $body;
        $this->date_sent = $date_sent;
        $this->threadChild = null;
    }

    public function addChild(&$mail) {
        if(!$this->threadChild) {
            $this->threadChild = array();
        }
        $this->threadChild[] = $mail;
    }

    public function getSubject(){
        return $this->subject;
    }

    public function hasChild() {
        return $this->threadChild!=null;
    }

    public function getChildren() {
        return $this->threadChild;
    }

    public function printMail() {
        echo "Subject : {$this->subject}\n";
        echo "From : {$this->senderName} <{$this->senderMailID}>\n";
        echo "Date : {$this->date_sent}\n";
        echo "\n-------------------------------------------------------------------\n";
        echo $this->body;
        echo "__________________________________________________________________________________________________________\n";
    }
}

$ma = new MailArchive('forum_justnetcoalition.org','forum@JNC','forum@justnetcoalition.org','http://justnetcoalition.org/mailman/private');

// $ma->parseMonthlyArchiveByDate('December','2014');
$ma->printAvailableMonths();
//$allMails = $ma->getAllMails('December','2014');
$ma->parseMonthlyArchiveByThread('December','2014');
//$ma->printThread();

//foreach ($allMails as $key => $mail) {
//    $mail->printMail();
//    echo "\n";
//}

function getArgs() {
    $shortopts = "o:e:f:l:";
    $longopts = array(
        "operation:",
        "email:",
        "first_name:",
        "last_name:",
    );
    $args = array(
        'operation' => '',
        'email' => '',
        'first_name' => '',
        'last_name' => '',
    );

    $options = getopt($shortopts,$longopts);
    if(array_key_exists('o', $options)) {
        $args['operation'] = $options['o'];
    } else if(array_key_exists('operation', $options)) {
        $args['operation'] = $options['operation'];
    }
    if(array_key_exists('e', $options)) {
        $args['email'] = $options['e'];
    } else if(array_key_exists('email', $options)) {
        $args['email'] = $options['email'];
    }
    if(array_key_exists('f', $options)) {
        $args['first_name'] = $options['f'];
    } else if(array_key_exists('first_name', $options)) {
        $args['first_name'] = $options['first_name'];
    }
    if(array_key_exists('l', $options)) {
        $args['last_name'] = $options['l'];
    } else if(array_key_exists('last_name', $options)) {
        $args['last_name'] = $options['last_name'];
    }
    return $args;
}

function process_request() {
    $options = getArgs();
    var_dump($options);
    $l = new SubscriberList('forum_justnetcoalition.org', 'forum@JNC', 'forum@justnetcoalition.org', 'http://justnetcoalition.org/mailman' , 'iso-8859-1');

    switch ($options['operation']) {
        case 'subscribe':
        case 's':
            $email = $options['email'];
            $first_name = $options['first_name'];
            $last_name = $options['last_name'];
            if($email == '' ) {
                echo "Email not given";
                break;
            }
            $message = $l->subscribe($email, $first_name, $last_name);
            echo "{$message}\n";
            break;

        case 'unsubscribe':
        case 'u':
            $email = $options['email'];
            if($email == '') {
                echo "Email not given";
                break;
            }
            $message = $l->unsubscribe($email);
            echo "{$message}\n";
            break;

        case 'list':
        case 'l':
            $members = $l->get_all_members();
            print_r($members);
            echo "Total members : ".count($members)."\n";
            break;

        default:
            echo "Invalid operation requested!!!";
            break;
    }
}
// process_request();