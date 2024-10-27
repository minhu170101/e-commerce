<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%products}}".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $image
 * @property float $price
 * @property int $status
 * @property int $category_id
 * @property int $sub_category_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $created_by
 * @property string $updated_by
 *
 * @property CartItems[] $cartItems
 * @property SubCategories $category
 * @property OrderItems[] $orderItems
 * @property SubCategories $subCategory
 */
class Products extends ActiveRecord
{   
    /**
     * @var UploadedFile
     */
    public $imageFile;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%products}}';
    }

    public function behaviors(){
        return [
            TimestampBehavior::class => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => new \yii\db\Expression('NOW()'),
            ],
            BlameableBehavior::class => [
                'class' => BlameableBehavior::class,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'price', 'status', 'category_id', 'sub_category_id'], 'required'],
            [['description'], 'string'],
            [['price'], 'number'],
            [['imageFile'], 'image', 'extensions' => 'png, jpg, jpeg, webp', 'maxSize' => 10 * 1024 * 1024],
            [['status', 'category_id', 'sub_category_id'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['name', 'image', 'created_by', 'updated_by'], 'string', 'max' => 255],
            [['category_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubCategories::class, 'targetAttribute' => ['category_id' => 'category_id']],
            [['sub_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubCategories::class, 'targetAttribute' => ['sub_category_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'description' => 'Description',
            'image' => 'Preview Image',
            'imageFile' => 'Product Image',
            'price' => 'Price',
            'status' => 'Published',
            'category_id' => 'Category ID',
            'sub_category_id' => 'Sub Category ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
        ];
    }

    /**
     * Gets query for [[CartItems]].
     *
     * @return \yii\db\ActiveQuery|\common\models\query\CartItemsQuery
     */
    public function getCartItems()
    {
        return $this->hasMany(CartItems::class, ['product_id' => 'id']);
    }

    /**
     * Gets query for [[Category]].
     *
     * @return \yii\db\ActiveQuery|\common\models\query\SubCategoriesQuery
     */
    public function getCategory()
    {
        return $this->hasOne(SubCategories::class, ['category_id' => 'category_id']);
    }

    /**
     * Gets query for [[OrderItems]].
     *
     * @return \yii\db\ActiveQuery|\common\models\query\OrderItemsQuery
     */
    public function getOrderItems()
    {
        return $this->hasMany(OrderItems::class, ['product_id' => 'id']);
    }

    /**
     * Gets query for [[SubCategory]].
     *
     * @return \yii\db\ActiveQuery|\common\models\query\SubCategoriesQuery
     */
    public function getSubCategory()
    {
        return $this->hasOne(SubCategories::class, ['id' => 'sub_category_id']);
    }

    /**
     * {@inheritdoc}
     * @return \common\models\query\ProductsQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \common\models\query\ProductsQuery(get_called_class());
    }

    public function save($runValidation = true, $attributeNames = null) {
        if ($this->imageFile instanceof UploadedFile) {
            $this->image = '/products/' . Yii::$app->security->generateRandomString() . '/' . $this->imageFile->name;
        } else {
            Yii::error('No valid file instance', __METHOD__);
        }

        $transaction = Yii::$app->db->beginTransaction();
        $ok = parent::save($runValidation, $attributeNames);
        if ($ok && $this->imageFile instanceof UploadedFile) {
            $fullPath = Yii::getAlias('@frontend/web/storage' . $this->image);
            $dir = dirname($fullPath);

            Yii::info('Saving file to: ' . $fullPath, __METHOD__);

            if (!FileHelper::createDirectory($dir)) {
                Yii::error('Failed to create directory: ' . $dir, __METHOD__);
            } 

            if (!$this->imageFile->saveAs($fullPath)) {
                Yii::error('Failed to save file to: ' . $fullPath, __METHOD__);
                $transaction->rollBack();
                return false;
            }
        } else {
            Yii::error('Failed to save model', __METHOD__);
        }

        $transaction->commit();
        return $ok;
    }

    public function getImageUrl()
    {
        return self::formatImageUrl($this->image);
    }

    public static function formatImageUrl($imagePath)
    {
        if ($imagePath) {
            return Yii::$app->params['frontendUrl'] . '/storage' . $imagePath;
        }

        return Yii::$app->params['frontendUrl'] . '/img/Image-not-found.png';
    }

    public function getShortDescription()
    {
        return \yii\helpers\StringHelper::truncateWords(strip_tags($this->description), 30);
    }

    public function afterDelete()
    {
        parent::afterDelete();
        if ($this->image) {
            $dir = Yii::getAlias('@frontend/web/storage'). dirname($this->image);
            FileHelper::removeDirectory($dir);
        }
    }

}

