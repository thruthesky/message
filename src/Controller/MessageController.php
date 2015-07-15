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
        if ( $page == 'list' || $page == 'unread' || $page == 'sent' ) $page = 'collect';
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
		$input = self::input();		
		//just used for total...
        $result = db_select('we_message')->fields(null, ['id']);
		if( $data['page'] == 'list' ) $result = $result->condition( 'user_id' , self::uid() );
		else if( $data['page'] == 'unread' ){
			$result = $result->condition( 'user_id' , self::uid() );
			$result = $result->condition( 'checked' , 0 );
		}
		else if( $data['page'] == 'sent' ) $result = $result->condition( 'send_id' , self::uid() );		
		$result = $result->orderBy('id', 'DESC')->execute();
		
		$total_ids = [];
		while ( $row = $result->fetchAssoc(\PDO::FETCH_ASSOC) ) {
			$total_ids[] = $row['id'];
		}
			
		$per_page = 10;
		$total_messages = count( $total_ids );
		$total_pages = ceil( count( $total_ids ) / $per_page );
		
		if( !empty( $input['page_num'] ) ){
			if( $input['page_num'] <= 0 ){
				$data['error'] = 'Page number cannot be less than 1!';
				return;
			}
			else if ( $input['page_num'] > $total_pages ){
				$data['error'] = 'Page number cannot be more than the total pages';
				return;
			}
			
			$page_num = $input['page_num'];
			$from = ( $input['page_num'] -1 ) * $per_page;
		}
		else{
			$page_num = 1;
			$from = 0;
		}
		
        $result_paged = db_select('we_message')->fields(null, ['id']);
        if( $data['page'] == 'list' ) $result_paged = $result_paged->condition( 'user_id' , self::uid() );
		else if( $data['page'] == 'unread' ){
			$result_paged = $result_paged->condition( 'user_id' , self::uid() );
			$result_paged = $result_paged->condition( 'checked' , 0 );
		}
		else if( $data['page'] == 'sent' ) $result_paged = $result_paged->condition( 'send_id' , self::uid() );
		$result_paged = $result_paged->orderBy('id', 'DESC')->range( $from , $per_page)->execute();
			
        $ids = [];
        while ( $row = $result_paged->fetchAssoc(\PDO::FETCH_ASSOC) ) {
            $ids[] = $row['id'];
        }
		
		$data['total_messages'] = $total_messages;
		$data['total_pages'] = $total_pages;
		$data['page_num'] = $page_num;
		$data['per_page'] = $per_page;

        $data['list'] = Message::loadMultiple($ids);
    }
	
	/*
    private static function unread(&$data) {	
        $result = db_select('we_message')
            ->fields(null, ['id'])
            ->condition( 'user_id' , self::uid() )
			->condition( 'checked', 0 )				
			->orderBy('id', 'DESC')
			->execute();
			
        $ids = [];
        while ( $row = $result->fetchAssoc(\PDO::FETCH_ASSOC) ) {
            $ids[] = $row['id'];
        }
		$data['page_type'] = 'unread';
		$data['total_messages'] = count( $result );
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
		$data['page_type'] = 'sent';
		$data['total_messages'] = count( $result );
        $data['list'] = Message::loadMultiple($ids);
    }
	*/
	
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
        $message= Message::load(\Drupal::request()->get('id'));
		if( $message->checked->value == 0 ){
			$message->set( 'checked',1 );
			$message->save();
		}
		
		$data['page_type'] = 'view';
		$data['message'] = $message;
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