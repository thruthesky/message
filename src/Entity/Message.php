<?php
namespace Drupal\message\Entity;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;

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

    public static function sendFrom(&$data) {
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
        return false;
    }


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
