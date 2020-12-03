# ImgUpload

![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/exinfinite/img-upload)
![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/exinfinite/ImgUpload)
![Packagist Version](https://img.shields.io/packagist/v/exinfinite/img-upload)
![Packagist Downloads](https://img.shields.io/packagist/dt/exinfinite/img-upload)
![GitHub](https://img.shields.io/github/license/exinfinite/ImgUpload)

> 需要gd、exif extension

## 安裝


```php
composer require exinfinite/img-upload
```

## 使用

### 初始化

```php
//支援jpg、jpeg、png
$img = new Exinfinite\ImgUpload\ImgExif("upload dir", "name of input[type=file]");
```

### 基本用法

```php
$fileinfo = $img->setFitSize(1920)->save();//max-width:1920
```

### 常用設定

```php
$filename = "filename without extension";//程式自動判斷檔案類型
$img->getDimension();//圖檔尺寸
$img->getExif();//EXIF資訊
$img->md5Hash();//圖檔hash
$img->sizeLimit("10m");//default:20m
$img->mkdir("dir path");//建立資料夾
$img->setFitSize("max width", "max height");//自動縮圖,0為不限制
$img->save("dir_path/{$filename}");//儲存處理後的檔案
$img->upload("dir_path/{$filename}");//上傳原始圖檔,若需要此操作,需放最後執行
```
