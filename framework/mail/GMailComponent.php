<?php

/**
 * Class GMailComponent
 *
 * @property Swift_Transport $transport
 * @property Swift_Mailer $mailer
 */
class GMailComponent extends GDICComponent
{
    public function init()
    {
        Yii::setPathOfAlias('swift',__DIR__.'/../vendors/swift/lib/');
        Yii::setPathOfAlias('gmail',__DIR__);
        Yii::import('swift.classes.Swift');
        Yii::registerAutoloader(array('Swift','autoload'),true);
        Yii::import('swift.swift_init',true);
        Yii::import('gmail.GMailTransportComponent');

        parent::init();
    }

    /**
     * @param null $subject
     * @param null $body
     * @param null $contentType
     * @param null $charset
     * @return Swift_Message
     */
    public function getNewMessage($subject = null, $body = null, $contentType = null, $charset = null)
    {
        return Swift_Message::newInstance($subject, $body, $contentType, $charset);
    }

}
