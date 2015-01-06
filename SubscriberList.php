<?php


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
