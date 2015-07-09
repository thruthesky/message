<?php
namespace Drupal\message\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\message\Entity\Message;
use Symfony\Component\HttpFoundation\RedirectResponse;


class MessageController extends ControllerBase {
    private static $input;

    private static function theme($data) {
        return [
            '#theme' => 'message.layout',
            '#data' => $data,
        ];
    }

    public static function defaultController($page) {
        $data = ['page'=>$page];
        $data['input'] = self::input();
        if ( ! self::checkLogin($data) ) return self::theme($data);
        if ( $page == 'list' ) $page = 'collect';
        if ( $render = self::$page($data) )  return $render;
        else return self::theme($data);
    }
    private static function checkLogin(&$data) {
        if ( $uid = self::uid() ) {
            return $uid;
        }
        else {
            $data['error'] = 'Please, login first to access this page.';
            $data['error_title'] = 'Login Error';
            return false;
        }
    }
    private static function uid() {
        return \Drupal::currentUser()->getAccount()->id();
    }
    private static function collect(&$data) {
        $result = db_select('we_message')
            ->fields(null, ['id'])
            ->condition('user_id', self::uid())
            ->orderBy('id', 'DESC')
            ->execute();
        $ids = [];
        while ( $row = $result->fetchAssoc(\PDO::FETCH_ASSOC) ) {
            $ids[] = $row['id'];
        }
        $data['list'] = Message::loadMultiple($ids);
    }


    private static function sent(&$data) {
        $result = db_select('we_message')
            ->fields(null, ['id'])
            ->condition('send_id', self::uid())
            ->orderBy('id', 'DESC')
            ->execute();
        $ids = [];
        while ( $row = $result->fetchAssoc(\PDO::FETCH_ASSOC) ) {
            $ids[] = $row['id'];
        }
        $data['list'] = Message::loadMultiple($ids);
    }

    private static function send(&$data) {
        $request = \Drupal::request();
        if ( $request->get('mode') == 'submit' ) {
            $data['error'] = Message::sendFrom($data);
            if ( $data['error'] ) {
            }
            else {
                return new RedirectResponse('/message/list');
            }
        }
    }


    private static function view(&$data) {
        $data['message'] = Message::load(\Drupal::request()->get('id'));
    }

    private static function input() {

        if ( empty(self::$input) ) {
            $request = \Drupal::request();
            $get = $request->query->all();
            $post = $request->request->all();
            self::$input = array_merge( $get, $post );
        }
        return self::$input;
    }


    private static function delete(&$data) {
        $request = \Drupal::request();

        $message = Message::load($request->get('id', 0));
        if ( empty($message) ) {
            $data['error'] = "Message does not exist.";
            return false;
        }

        if ( $message->getOwnerId() == self::uid() ) {
            $data['error'] = "This is not your message. you cannot delete";
        }

        $message->delete();
        return new RedirectResponse('/message/list');
    }

}