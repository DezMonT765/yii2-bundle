<?php

namespace dezmont765\yii2bundle\actions;

use dezmont765\yii2bundle\widgets\PartialActiveForm;
use yii\web\Response;

/**
 * Created by PhpStorm.
 * User: Dezmont
 * Date: 11.05.2017
 * Time: 12:07
 */
class AjaxValidationWithDynamicChildrenAction extends MultipleDynamicFieldsAction
{


    public function getModel($id) {
        $this->model_class = $this->getModelClass();
        $model = $this->controller->findModel($this->model_class, $id);
        if(!$model instanceof $this->model_class) {
            $model_class = $this->model_class;
            $model = new $model_class;
        }
        return $model;
    }


    public function run($id = null) {
        parent::run($id);
        \Yii::$app->response->format = Response::FORMAT_JSON;
        if(\Yii::$app->request->isAjax) {
            $this->findExistingSubModels();
            $this->initModels();
            $result = [];
            PartialActiveForm::ajaxValidation($result, $this->model);
            foreach($this->fields as $field) {
                PartialActiveForm::ajaxValidationMultiple($result, $field[self::CHILD_MODELS]);
            }
            return $result;
        }
        else return null;
    }
}