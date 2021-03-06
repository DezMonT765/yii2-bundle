<?php
namespace dezmont765\yii2bundle\actions;

use dezmont765\yii2bundle\components\geometry\IllegalArgumentException;
use dezmont765\yii2bundle\events\DynamicChildrenAfterDataLoadEvent;
use dezmont765\yii2bundle\events\DynamicChildrenActionAfterSaveEvent;
use dezmont765\yii2bundle\events\DynamicChildrenActionBeforeSaveEvent;
use dezmont765\yii2bundle\models\AExtendableActiveRecord;
use dezmont765\yii2bundle\models\ADependentActiveRecord;
use dezmont765\yii2bundle\models\MainActiveRecord;
use dezmont765\yii2bundle\widgets\PartialActiveForm;
use Yii;
use yii\base\Controller;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * Created by PhpStorm.
 * User: Dezmont
 * Date: 08.05.2017
 * Time: 11:54
 * @property MainActiveRecord $model . This is a "parent" model .
 * @property AExtendableActiveRecord $sub_model_parent_class
 * @property ADependentActiveRecord $sub_model_class
 * @property DynamicChildrenProcessor[] | array $fields
 * @property DynamicChildrenProcessor|string $fields_processor
 * This action allows you to process the model with it's children using the only one form
 */
abstract class DynamicChildrenAction extends MainAction
{

    const BEFORE_SAVE = 'afterModelLoad';
    const AFTER_UNSUCCESSFUL_SAVE = 'afterUnsuccessfulSave';
    const AFTER_SUCCESSFUL_SAVE = 'afterSuccessfulSave';
    public $model = null;
    public $fields = [];
    public $id_param_name = 'id';
    const FIELDS_PROCESSOR = 'fields_processor';
    const EVENTS = 'events';
    const ID_PARAM_NAME = 'id_param_name';


    public function __construct($id, Controller $controller, array $config = []) {
        $this->_events = $this->events();
        parent::__construct($id, $controller, $config);
    }


    /**
     * Transforms array of settings into the @see DynamicChildrenProcessor objects
     * @throws InvalidConfigException
     */
    public function init() {
        parent::init(); // TODO: Change the autogenerated stub
        foreach($this->fields as $key => &$fields) {
            if(!isset($fields[self::FIELDS_PROCESSOR])) {
                throw new InvalidConfigException('Fields processor should be specified');
            }
            $fields_processor_class = $fields[self::FIELDS_PROCESSOR];
            unset($fields[self::FIELDS_PROCESSOR]);
            $fields = new $fields_processor_class($fields);
        }
        foreach($this->_events as $name => $event) {
            foreach($event as $class => $handlers) {
                foreach($handlers as $handler) {
                    Event::on($class, $name, $handler);
                }
            }
        }
    }


    public function events() {
        return [
            DynamicChildrenProcessor::AFTER_LOAD_CHILD_MODELS_EVENT => [
                DynamicChildrenProcessor::class => [
                    function (DynamicChildrenAfterDataLoadEvent $event) {
                        $processor = $event->field_processor;
                        if($processor->child_models_parent_class) {
                            $child_models_parent_class = $processor->getChildModelsMainClass();
                            if(empty($processor->child_models)) {
                                $child_model = new $child_models_parent_class;
                                $child_model->category = $processor->category;
                                $processor->child_models[] = $child_model;
                            }
                        }
                    }
                ]
            ]
        ];
    }


    private $_events = [];


    public function setEvents(array $events = []) {
        $this->_events = $events;
    }


    public function getEvents() {
        if($this->_events === null) {
            return self::events();
        }
        else return $this->_events;
    }


    /**
     * For each @see DynamicChildrenProcessor performs data loading
     */
    public function loadChildModelsFromRequest() {
        if(!empty(Yii::$app->request->post())) {
            foreach($this->fields as &$fields) {
                $fields->loadChildModelsFromRequest();
                $fields->afterLoadChildModels();
            }
        }
    }


    public function beforeSave() {
        $event = new DynamicChildrenActionBeforeSaveEvent(['model' => $this->model]);
        $this->trigger(self::BEFORE_SAVE, $event);
        return $event->is_valid;
    }


    public function afterSuccessfulSave() {
        if($this->hasEventHandlers(self::AFTER_SUCCESSFUL_SAVE)) {
            $event = new DynamicChildrenActionAfterSaveEvent(['model' => $this->model]);
            $this->trigger(self::AFTER_SUCCESSFUL_SAVE, $event);
            return $event->result;
        }
        return $this->controller->redirect(['update', 'id' => $this->model->id]);
    }


    public function afterUnsuccessfulSave() {
        if($this->hasEventHandlers(self::AFTER_UNSUCCESSFUL_SAVE)) {
            $event = new DynamicChildrenActionAfterSaveEvent(['model' => $this->model]);
            $this->trigger(self::AFTER_UNSUCCESSFUL_SAVE, $event);
            return $event->result;
        }
        return null;
    }


    /**
     * Saves the "parent" model and it's children, described in the @see DynamicChildrenProcessor objects.
     * @return \yii\web\Response
     */
    public function save() {
        if($this->model->load(Yii::$app->request->post())) {
            if($this->beforeSave()) {
                if($this->model->save()) {
                    foreach($this->fields as &$field) {
                        $field->saveChildModels($this->model);
                    }
                    return $this->afterSuccessfulSave();
                }
            }
            return $this->afterUnsuccessfulSave();
        }
        return null;
    }


    /**
     * Searches children using info from @see DynamicChildrenProcessor object
     */
    public function findChildModels() {
        if(!$this->model->isNewRecord) {
            foreach($this->fields as &$field) {
                $field->findChildModels($this->model);
            }
        }
    }


    /**
     * Gets the "parent" model
     * @param $id
     * @return mixed
     */
    abstract public function getModel($id);


    public function beforeRun() {
        $id = Yii::$app->request->get('id');
        $this->model = $this->getModel($id);
        $this->model->load(Yii::$app->request->post());
        $this->model->load(Yii::$app->request->get());
        foreach($this->fields as $field) {
            $field->model = $this->model;
        }
        return true;
    }


    public function getDefaultView() {
        return $this->controller->id . '-form';
    }


    /**
     * Returns the "parent" model class
     * @return array|mixed|null
     */
    public function getModelClass() {
        $model_class = parent::getModelClass();
        $model_class = $model_class ?? Yii::$app->request->get('model_class');
        return $model_class;
    }


}