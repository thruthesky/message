<?php
namespace Drupal\message\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\Client;
use Drupal\library\Library;
use Drupal\library\Member;
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
		if( $page == 'admin' ){
			$member = Member::load( Library::myuid() );
			$role = $member->roles->target_id;
			if( $role != 'administrator' ){
				$data['error'] = 'You are not an admin';
				$data['page'] = 'admin';				
			}			
			$page = 'collect';
		}
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
	//changed into public by benjamin ( used it in message.module )
    public static function uid() {
        return \Drupal::currentUser()->getAccount()->id();
    }
    private static function collect(&$data) {
		//di( user_load_by_name("admian") );
	
		$uri = \Drupal::request()->getRequestUri();
		$search_mode = false;
		if( strpos( $uri, '/message/search' ) !== false ) $search_mode = true;
		
        $input = self::input();
        //just used for total...
        $result = db_select('we_message')->fields(null, ['id']);
        if( $data['page'] == 'list' ) $result = $result->condition( 'user_id' , self::uid() );
        else if( $data['page'] == 'unread' ){
            $result = $result->condition( 'user_id' , self::uid() );
            $result = $result->condition( 'checked' , 0 );
        }
        else if( $data['page'] == 'sent' ) $result = $result->condition( 'send_id' , self::uid() );
		else if( $data['page'] == 'admin' ){}
		
		/*search*/
		if( $search_mode == true && !empty( $input['keyword'] ) ){
			//$db->condition('url_from', '%'.$keyword_from.'%', 'LIKE');
			$ors = db_or();
			$ors->condition('title', '%'.$input['keyword'].'%', 'LIKE');
			$ors->condition('content', '%'.$input['keyword'].'%', 'LIKE');
			
			
			$member = user_load_by_name( $input['keyword'] );
			if( !empty( $member ) ){
				$ors->condition('send_id', '%'.$member->id().'%', 'LIKE');
				$ors->condition('user_id', '%'.$member->id().'%', 'LIKE');
			}
			
			$result = $result->condition( $ors );			
		}
		/*eo search*/
		
        $result = $result->orderBy('id', 'DESC')->execute();

        $total_ids = [];
        while ( $row = $result->fetchAssoc(\PDO::FETCH_ASSOC) ) {
            $total_ids[] = $row['id'];
        }
		if( !empty( $input['limit'] ) ){
			$per_page = $input['limit'];			
		}
        else $per_page = 10;

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
		else if( $data['page'] == 'admin' ){}
		
		/*search*/
		if( $search_mode == true ){
			//$db->condition('url_from', '%'.$keyword_from.'%', 'LIKE');
			$ors = db_or();
			$ors->condition('title', '%'.$input['keyword'].'%', 'LIKE');
			$ors->condition('content', '%'.$input['keyword'].'%', 'LIKE');
			
			if( !empty( $member ) ){//send id only
				$ors->condition('send_id', '%'.$member->id().'%', 'LIKE');
				$ors->condition('user_id', '%'.$member->id().'%', 'LIKE');
			}
			
			$result_paged = $result_paged->condition( $ors );						
		}
		/*eo search*/
		
        $result_paged = $result_paged->orderBy('id', 'DESC')->range( $from , $per_page)->execute();

        $ids = [];
        while ( $row = $result_paged->fetchAssoc(\PDO::FETCH_ASSOC) ) {
            $ids[] = $row['id'];
        }		
        $data['total_messages'] = $total_messages;
        $data['total_pages'] = $total_pages;
        $data['page_num'] = $page_num;
        $data['per_page'] = $per_page;

        $list = Message::loadMultiple($ids);
		$messages = [];
		foreach( $list as $k=>$v ){
			$messages[$k]['entity'] = $v;
			$messages[$k]['no_of_files'] =  count( Library::files_by_module_id('message', $v->id() ) );
		}
		
		$data['list'] = $messages;		
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
            $id = Message::sendForm($data);
			$fids = $request->get("fid");
			
			if( !empty( $fids ) ){
				//di( $fids );
				Message::updateUploadedFiles( $id );
				//exit;
			}
            if ( is_numeric($id) ) {
				if( !empty( $request->get('custom_sms_message') ) ) $custom_message = $request->get('custom_sms_message');
				else $custom_message = null;
                self::sendSMS($id,$custom_message);
				$message = Message::load( $id );
				if( $message->user_id->target_id != Library::myuid() ) $redirect_url = '/message/sent';
				else $redirect_url = '/message/list';
                return new RedirectResponse( $redirect_url );
            }
            else {
                $data['error']  = $id;
            }
        }
    }


    private static function view(&$data) {
        $message= Message::load(\Drupal::request()->get('id'));
        if( $message->checked->value == 0 ){
            if( self::uid() == $message->user_id->target_id ){
                //only mark as read when viewed by the receiver
                $message->set( 'checked', time() );
                $message->save();
            }
        }

        $data['page_type'] = 'view';
        $data['message'] = $message;
					
		$data['sender'] = Member::load( $message->send_id->target_id );
		
		$data['files'] =  Library::files_by_module_id('message', $message->id() );
		$data['rendered_files'] = Message::renderViewFiles($data['files']);		
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

        $id = $request->get('id');
        $ids = $request->get('ids');
        if ( empty($id) && empty($ids) ) {
            $data['error'] = "Wrong input. ID(s) is not provided.";
            return false;
        }
        if ( $id ) $ids = [ $id ];

        foreach( $ids as $id ) {
            $message = Message::load( $id );
            if ( empty($message) ) {
                $data['error'] = "Message does not exist.";
                return false;
            }
            //getOwnerId here is only the receiver
            if ( $message->getOwnerId() != self::uid() ) {
                //$page = 'sent';
                if( $message->send_id->target_id == self::uid() ){
                    if( $message->checked->value == 0 ) $message->delete();
                    else $data['error'] = "You cannot delete sent messages that are already read by the receiver.";
                }
                else $data['error'] = "This is not your message. you cannot delete. [ ID: ".$message->id()." ]";
            }
            else {
                //$page = 'list';
                $message->delete();
            }
        }        
        $data['input'] = self::input();
		$data['page'] = $data['input']['page'];
        self::collect( $data );
        return self::theme($data);

        //return new RedirectResponse('/message/list');
    }
	
	private static function read(&$data) {
        $request = \Drupal::request();

        $ids = $request->get('ids');
        if ( empty($id) && empty($ids) ) {
            $data['error'] = "Wrong input. ID(s) is not provided.";
            return false;
        }

        foreach( $ids as $id ) {
            $message = Message::load( $id );
            if ( empty($message) ) {
                $data['error'] = "Message does not exist.";
                return false;
            }
            //getOwnerId here is only the receiver
            if ( $message->getOwnerId() != self::uid() ) {
               $data['error'] = "You cannot mark sent messages as read.";
            }
            else {
                //$page = 'list';
                $message->set( "checked",time() );
				$message->save();
            }
        }
		
        //$data['page'] = $page;
        $data['input'] = self::input();
		$data['page'] = $data['input']['page'];
        self::collect( $data );
        return self::theme($data);

        //return new RedirectResponse('/message/list');
    }

    private static function sendSMS( $id, $custom_message = null )
    {
        Library::log("sendSMS()");
        $request = \Drupal::request();
        $username = $request->get('receiver');
        $user = user_load_by_name($username);
        $uid = $user->id();

        // https://docs.google.com/document/d/1koxonGQl20ER7HZqUfHd6L53YXT5fPlJxCEwrhRqsN4/edit#heading=h.t8chdb9o7djs
        if ( Member::isOnline($username) ) return;
        Library::log("$username is offline");

        // https://docs.google.com/document/d/1koxonGQl20ER7HZqUfHd6L53YXT5fPlJxCEwrhRqsN4/edit#heading=h.t8chdb9o7djs
        $stamp = intval(Member::get($uid, 'stamp_last_sms'));
        if ( $stamp + 60 * 60 > time() ) return;
        Library::log("$username got SMS before than 1 hour. He got last SMS on : " . date('r', $stamp));

        $client = new Client();
        $member = Member::load($uid);
        $number = isset($member->extra['mobile']) ? $member->extra['mobile'] : null;
        if ( $number ) {			
			

			
            $count = Message::countNew($uid);
			if( !empty( $custom_message ) ) $message = $custom_message;
			else $message = "You have $count new message(s) on www.sonub.com";
			$number = self::adjustNumber( $number );
			
			if ( ! is_numeric($number) ) $error = "$number - Number is not Numeric";
            if ( strlen($number) != 11 ) $error = "$number - Number must be 11 digits. This number $re[number] is not in 11 digits.";
            if ( $number[2] == '0' && $number[3] == '0' ) $error = "$number - Number cannot have 0 on the 3rd and 4th position of the numbers.";
            if ( strlen($message) > 159 ) $error = "$number - Message must be shorter than 159 letters.";
			
			if( !empty( $error ) ){
				Library::log( $error );
			}
			else{		
				$url = "http://dev.withcenter.com/smsgate/send?username=withcenter&password=Wc0453224133&number=$number&message=$message&priority=3";
				Library::log("SMS Sending URL: $url");
				$response = $client->post($url, ['verify'=>false]);
				$code = $response->getStatusCode();
				$re = $response->json();
				if ( isset($re['error']) && $re['error'] ) {
					$message = Message::load($id);
					$message->set('result_sms_send', 'F');
					$message->save();
					Library::log("SMS failed");
				}
				else {
					Library::log("SMS Success");
					Member::set($uid, 'stamp_last_sms', time());
				}
			}
        }
        else {
            Library::log("$username has no number");
        }


    }
	
	private static function adjustNumber($number)
    {
        $number = preg_replace("/[^0-9]/", '', $number); // remove all characters
        $number = str_replace("639", "09", $number);
        $number = str_replace("630", "0", $number);
        $number = str_replace("63", "0", $number);
        return $number;
    }
}
