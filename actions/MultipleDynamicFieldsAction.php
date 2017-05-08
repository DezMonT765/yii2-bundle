<?php

namespace dezmont765\yii2bundle\actions;

use dezmont765\yii2bundle\models\AParentActiveRecord;
use dezmont765\yii2bundle\models\ASubActiveRecord;
use dezmont765\yii2bundle\models\MainActiveRecord;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Created by PhpStorm.
 * User: Dezmont
 * Date: 30.04.2017
 * Time: 14:47
 * @property AParentActiveRecord $sub_model_parent_class
 * @property ASubActiveRecord $sub_model_class
 * @property ActiveRecord $model
 */
abstract class MultipleDynamicFieldsAction extends DynamicFieldsAction
{

    public $fields = [];


    public function initialValues() {
        return [
            self::BINDING_CLASS => null,
            self::SUB_MODELS => [],
            self::SUB_MODEL_CLASS => null,
            self::CATEGORY_POST_PARAM => 'category',
        ];
    }


    public function init() {
        parent::init(); // TODO: Change the autogenerated stub
        foreach($this->fields as $key => &$fields) {
            /**
             * @var $key MainActiveRecord
             */
            $post = Yii::$app->request->getBodyParam($key::_formName());
            $category = $post[$fields[self::CATEGORY_POST_PARAM]];
            $fields = ArrayHelper::merge($this->initialValues(), $fields);
            $fields[self::CATEGORY] =
                $this->getCategory($fields[self::CATEGORY_GET_STRATEGY], $category);
            if($fields[self::CATEGORY]) {
                $fields[self::SUB_MODEL_CLASS] =
                    $this->getSubModelClass($fields[self::SUB_MODEL_CLASS], $fields[self::CATEGORY],
                                            $fields[self::SUB_MODEL_PARENT_CLASS]);
            }
        }
    }


    public function initModels() {
        foreach($this->fields as &$fields) {
            $this->loadModelsFromRequest($fields[self::SUB_MODELS], $fields[self::SUB_MODEL_CLASS]);
        }
    }


    public function save() {
        if($this->model->load(Yii::$app->request->post())) {
            if($this->model->save()) {
                foreach($this->fields as &$field) {
                    $this->saveSubModels($field[self::SUB_MODELS],
                                         $field[self::CATEGORY],
                                         $field[self::CHILD_BINDING_ATTRIBUTE],
                                         $field[self::PARENT_BINDING_ATTRIBUTE]
                    );
                }
                return $this->controller->redirect(['update', 'id' => $this->model->id]);
            }
        }
    }


    public function findExistingSubModels() {
        foreach($this->fields as &$field) {
            $field[self::SUB_MODELS] = $this->findSubModels($field[self::SUB_MODEL_CLASS],
                                                            $field[self::SUB_MODEL_PARENT_CLASS],
                                                            $field[self::CATEGORY],
                                                            $field[self::BINDING_CLASS],
                                                            $field[self::CHILD_BINDING_ATTRIBUTE],
                                                            $field[self::PARENT_BINDING_ATTRIBUTE]
            );
        }
    }


}