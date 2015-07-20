<?php
namespace Drupal\message\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\file\Entity\File;
use Drupal\library\Library;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Drupal\message\Entity\Message;

class API extends ControllerBase {
	private static function fileUploadInfo() {
        $re = [];		
        foreach( $_FILES as $k => $v ) {
            $f = array();
            $f['form_name'] = $k;
            if ( is_array($v['name']) ) {
                for ($i = 0; $i < count($v['name']); $i++) {
                    $f['name'] = $v['name'][$i];
                    $f['type'] = $v['type'][$i];
                    $f['tmp_name'] = $v['tmp_name'][$i];
                    $f['error'] = $v['error'][$i];
                    $f['size'] = $v['size'][$i];
                    $re[] = $f;
                }
            }
            else {
                $f['name'] = $v['name'];
                $f['type'] = $v['type'];
                $f['tmp_name'] = $v['tmp_name'];
                $f['error'] = $v['error'];
                $f['size'] = $v['size'];
                $re[] = $f;
            }
        }
        return $re;
    }

    public function DefaultController() {	

        $call = \Drupal::request()->get('call');		
        $re = $this->$call();
        if ( is_array($re) ) {
        }
        else {
            $re = ['result'=>$re];
        }
        if ( !isset($re['code']) ) $re['code'] = 0;
        $re = json_encode($re);
        $response = new JsonResponse( $re );
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }
	
	public static function fileUpload() {		
        Library::log("fileUpload() begin");

        $uploads = self::fileUploadInfo();
		
        Library::log($uploads);
        file_prepare_directory($repo = DIR_MESSAGE_DATA, FILE_CREATE_DIRECTORY);

        $re = [];
        foreach( $uploads as $upload ) {
            Library::log("name: $upload[name], tmp_name: $upload[tmp_name]");	

            if (empty($upload['error'])) {
                $name = urlencode($upload['name']);
                if ( strlen($name) > 150 ) {
                    $pi = pathinfo($name);
                    $name = substr($pi['filename'], 0, 144) . '.' . $pi['extension'];
                }
                Library::log("name:$name");
                $path = $repo . $name;
                Library::log("path to save: $path");
                $file = file_save_data(file_get_contents($upload['tmp_name']), $path);
                if ($file) {
                    $upload['url'] = $file->url();
                    $upload['thumbnails'] = Message::getFileUrl( $file );
                    $upload['fid'] = $file->id();
                    $info['form_name'] = $upload['form_name'];
                    \Drupal::service('file.usage')->add($file, 'message', $upload['form_name'], 0); // refer buildguide
                    $file->set('status', 0)->save(); // refer #buildguide
                }
            }
            else {

            }
            $re[] = $upload;
        }

        return ['files'=>$re];
    }


    public static function fileDelete() {
        $request = \Drupal::request();
        $fid = $request->get('fid', 0);
        try {
            $file = File::load($fid);
            if ( $file ) {
                $file->delete();
                //\Drupal::service('file.usage')->delete($file);
                return ['fid'=>$fid];
            }
            else {
                return [
                    'code' => '-2',
                    'message'=>"Failed to delete file:$fid "
                ];
            }
        }
        catch ( EntityStorageException $e ) {
            return [
                'code' => '-1',
                'message'=>"Failed to delete file:$fid "
            ];
        }
    }

}