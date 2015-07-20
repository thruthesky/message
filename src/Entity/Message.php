<?php
namespace Drupal\message\Entity;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;

use Drupal\file\Entity\File;
/**
 * Defines the Message entity.
 *
 *
 * @ContentEntityType(
 *   id = "we_message",
 *   label = @Translation("We Message Entity"),
 *   base_table = "we_message",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class Message extends ContentEntityBase implements MessageInterface {

    public static function sendForm(&$data) {		
        $request = \Drupal::request();
        return self::send(
            [
                'receiver' => $request->get('receiver'),
                'sender' => \Drupal::currentUser()->getAccount()->getUsername(),
                'title' => $request->get('title'),
                'content' => $request->get('content'),
            ]
        );
    }

    /**
     * @param $m
     * @return int|mixed|null|string
     *
     *      - if there is no error, then integer of message id will be return.
     *      - if there is error, string will be returned.
     *
     */
    private static function send($m) {
        if ( empty($m['receiver']) ) return "Recipient is empty";
        $receiver = user_load_by_name($m['receiver']);
        if ( empty($receiver)) return "No recipient exists by that name";
        $sender = user_load_by_name($m['sender']);
        if ( empty($sender)) return "No sender exists by that name";

        $message = self::create();
        $message->set('user_id', $receiver->id());
        $message->set('send_id', $sender->id());
        $message->set('title', $m['title']);
        $message->set('content', $m['content']);
        $message->set('checked', 0);
        $message->save();
        return $message->id();
    }

	public static function updateUploadedFiles( $message_id ){
		$request = \Drupal::request();
		$ids = $request->get('fid');
		
		if ( $ids ) {
			$fids = explode(',', $ids);
			foreach( $fids as $fid ) {
				if ( empty($fid) || ! is_numeric($fid) ) continue;
				$file = File::load($fid);
				if ( $file ) {
					$file->set('status', 1)->save();
					db_update('file_usage')
						->fields(['id'=>$message_id])
						->condition('fid', $fid)
						->execute();
				}
			}
		}
	}
	
	public static function getFileUrl( $file_entity ) {
		$file_url = [];
		$file_url['fid'] = $file_entity->id();
		$file_url['url_original'] = $file_entity->url();
		$path = $file_entity->getFileUri();
		$file_url['url_thumbnail'] = entity_load('image_style', 'thumbnail')->buildUrl($path);
		$file_url['url_medium'] = entity_load('image_style', 'medium')->buildUrl($path);
		$file_url['url_large'] = entity_load('image_style', 'large')->buildUrl($path);
		return $file_url;
	}
	
	public static function renderViewFiles($files) {
        if ( empty($files) ) return false;
        $ret = [];
        foreach( $files as $file ) {						
            if( strpos( $file->filemime->value, "image" ) !== false  ){
				$rendered_image = [];
				$rendered_image['entity'] = $file;
				$rendered_image['thumbnails'] = self::getFileUrl( $file );
				$ret['images'][] = $rendered_image;
			}
			else{
				$rendered_file = [];
				$rendered_file['entity'] = $file;
				$rendered_file['url'] = $file->url();
				$ret['files'][] = $rendered_file;				
			}
        }

        return $ret;
    }
	
	/*
	public static function files($id) {
        return Library::files_by_module_id('message', $id);
    }
	*/
    /**
     * {@inheritdoc}
     */
    public function getCreatedTime() {
        return $this->get('created')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getChangedTime() {
        return $this->get('changed')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getOwner() {
        return $this->get('user_id')->entity;
    }

    /**
     * {@inheritdoc}
     */
    public function getOwnerId() {
        return $this->get('user_id')->target_id;
    }

    /**
     * {@inheritdoc}
     */
    public function setOwnerId($uid) {
        $this->set('user_id', $uid);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOwner(UserInterface $account) {
        $this->set('user_id', $account->id());
        return $this;
    }


    public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
        $fields['id'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('ID'))
            ->setDescription(t('The ID of the  entity.'))
            ->setReadOnly(TRUE);

        $fields['uuid'] = BaseFieldDefinition::create('uuid')
            ->setLabel(t('UUID'))
            ->setDescription(t('The UUID of the  entity.'))
            ->setReadOnly(TRUE);

        $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
            ->setLabel(t('Recv ID'))
            ->setDescription(t('The Drupal User ID who owns the message entity.'))
            ->setSetting('target_type', 'user');

        $fields['send_id'] = BaseFieldDefinition::create('entity_reference')
            ->setLabel(t('Sender ID'))
            ->setDescription(t('The Drupal User ID who sent the message entity.'))
            ->setSetting('target_type', 'user');

        $fields['langcode'] = BaseFieldDefinition::create('language')
            ->setLabel(t('Language code'))
            ->setDescription(t('The language code of entity.'));

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Created'))
            ->setDescription(t('The time that the entity was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the entity was last edited.'));


        $fields['checked'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Stamp of checked'))
            ->setDescription(t('The time that the message was read by user_id'))
        ->setDefaultValue(0);



        $fields['result_sms_send'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Result of SMS Scheduling'))
            ->setDescription(t('Result of SMS Scheduling'))
            ->setSettings(array(
                'default_value' => '',
                'max_length' => 1,
            ));





        $fields['title'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Title'))
            ->setDescription(t('Title of message entity'))
            ->setSettings(array(
                'default_value' => '',
                'max_length' => 512,
            ));

        $fields['content'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Content'))
            ->setDescription(t('Content of the message'))
            ->setSettings(array(
                'default_value' => '',
                'max_length' => 12345,
            ));

        return $fields;
    }
}
