<?php

class EMail {
    private $subject;
    private $senderName;
    private $senderMailID;
    private $body;
    private $date_sent;
    private $mailUIN;

    function __construct($mailID, $subject, $senderName, $senderMailID, $body, $date_sent) {
        $this->mailUIN = $mailID;
        $this->subject = $subject;
        $this->senderName = $senderName;
        $this->senderMailID = $senderMailID;
        $this->body = $body;
        $this->date_sent = $date_sent;
    }


    public function printMail() {
        echo "Subject : {$this->subject}\n";
        echo "From : {$this->senderName} <{$this->senderMailID}>\n";
        echo "Date : {$this->date_sent}\n";
        echo "\n-------------------------------------------------------------------\n";
        echo $this->body;
        echo "__________________________________________________________________________________________________________\n";
    }

    public function getSenderName()
    {
        return $this->senderName;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getDateSent()
    {
        return $this->date_sent;
    }

    public function getMailUIN()
    {
        return $this->mailUIN;
    }

    public function getSenderMailID()
    {
        return $this->senderMailID;
    }

    public function getSenderInfo()
    {
        return "{$this->senderName} <{$this->senderMailID}>";
    }
}
