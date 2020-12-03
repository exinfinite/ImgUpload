<?php
namespace Exinfinite\ImgUpload;

class ImgExif extends File {
    private $img_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
    protected $size_limit = '10m';
    protected $exif = null;
    private $max_width = 0;
    private $max_height = 0;
    private $quality_png = '9';
    private $quality_jpg = '85';
    private $type = null;
    const _HANDLERS = [
        IMAGETYPE_JPEG => [
            'resample' => 'imagecreatefromjpeg',
            'save' => 'imagejpeg',
        ],
        IMAGETYPE_PNG => [
            'resample' => 'imagecreatefrompng',
            'save' => 'imagepng',
        ],
        IMAGETYPE_GIF => [
            'resample' => 'imagecreatefromgif',
            'save' => 'imagegif',
        ],
    ];
    public function __construct($file_dir, $input_key = 'file') {
        parent::__construct($input_key, $file_dir);
        //$this->exif = $this->getExif();
    }
    public function setImgTypes(array $types = []) {
        $this->img_types = $types;
        return $this;
    }
    public function sizeLimit($size) {
        $this->size_limit = $size;
        return $this;
    }
    public function setFitSize($width = 0, $height = 0) {
        $this->max_width = (int) $width;
        $this->max_height = (int) $height;
        return $this;
    }
    public function setQualityPng($qty) {
        $this->quality_png = (int) $qty;
        return $this;
    }
    public function setQualityJpg($qty) {
        $this->quality_jpg = (int) $qty;
        return $this;
    }
    public function getType() {
        if (is_null($this->type)) {
            $this->type = exif_imagetype($this->getFile());
        }
        return $this->type;
    }
    public function getNameWithExtension($filename = null) {
        $filename = (!is_null($filename) && trim($filename) != '') ? $filename : join('_', [date('ymdhis'), uniqid()]);
        return sprintf('%s%s', $filename, image_type_to_extension($this->getType()));
    }
    protected function typeValidation() {
        return (
            count($this->img_types > 0) &&
            is_int($this->getType()) &&
            in_array($this->getType(), $this->img_types)
        );
    }
    public function validation() {
        return ($this->isUploadedFile() && $this->typeValidation() && $this->sizeValidation());
    }
    public function getDimension() {
        list($width, $height) = getimagesize($this->getFile());
        return [
            'width' => $width,
            'height' => $height,
        ];
    }
    public function getExif() {
        if (!is_null($this->exif)) {
            return $this->exif;
        }
        $map_cols = [
            'FILE' => [
                'FileName',
                'FileType',
                'MimeType',
                'FileSize',
                'FileDateTime',
            ],
            'COMPUTED' => [
                'Copyright.Photographer',
                'Copyright.Editor',
                'Height',
                'Width',
                'ApertureFNumber',
                'FocusDistance',
                'UserCommentEncoding',
                'UserComment',
                'Thumbnail.FileType',
                'Thumbnail.MimeType',
            ],
            'IFD0' => [
                'ImageDescription',
                'Make',
                'Model',
                'Orientation',
                'XResolution',
                'YResolution',
                'ResolutionUnit',
                'Software',
                'DateTime',
                'Artist',
                'YCbCrPositioning',
                'Copyright',
            ],
            'EXIF' => [
                'ExifVersion',
                'FlashPixVersion',
                'DateTimeOriginal',
                'DateTimeDigitized',
                'ApertureValue',
                'ShutterSpeedValue',
                'MaxApertureValue',
                'ExposureTime',
                'FNumber',
                'MeteringMode',
                'LightSource',
                'Flash',
                'ExposureMode',
                'WhiteBalance',
                'ExposureProgram',
                'ExposureBiasValue',
                'ISOSpeedRatings',
                'ComponentsConfiguration',
                'CompressedBitsPerPixel',
                'FocalLength',
                'FocalLengthIn35mmFilm',
                'ColorSpace',
                'ExifImageWidth',
                'ExifImageLength',
                'FileSource',
                'SceneType',
                'UndefinedTag:0xA431',
                'UndefinedTag:0xA434',
            ],
        ];
        $exif = collect(@exif_read_data($this->getFile(), 0, true));
        $map = collect($map_cols);
        return $this->exif = $map
            ->keys( /* $exif */)
            ->mapWithKeys(function ($type) use ($map, $exif) {
                return [
                    $type => $exif
                        ->only($type)
                        ->collapse()
                        ->intersectKey(
                            $map
                                ->only($type)
                                ->flatten()
                                ->flip()
                        )
                        ->map(function ($item, $key) {
                            return is_string($item) ? mb_convert_encoding($item, "UTF-8") : $item;
                        })
                        ->toArray(),
                ];
            })->toArray();
    }
    protected function resample($thumb) {
        if (!array_key_exists($this->getType(), self::_HANDLERS)) {
            return false;
        }
        $image = call_user_func(self::_HANDLERS[$this->getType()]['resample'], $this->getFile());
        if ($this->getType() == IMAGETYPE_PNG) {
            imagefill($thumb, 0, 0, 0x7fff0000);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagesavealpha($image, true);
        }
        return $image;

    }
    protected function resize() {
        extract($this->getDimension());
        $w_ratio = $this->max_width > 0 ? $this->max_width / $width : 1;
        $h_ratio = $this->max_height > 0 ? $this->max_height / $height : 1;
        if ($h_ratio >= $w_ratio) {
            $new_width = $this->max_width > 0 ? min($width, $this->max_width) : $width;
            $new_height = ceil($height * ($new_width / $width));
        } else {
            $new_height = $this->max_height > 0 ? min($height, $this->max_height) : $height;
            $new_width = ceil($width * ($new_height / $height));
        }
        $thumb = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($thumb, $this->resample($thumb), 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imageinterlace($thumb, true);
        $oren = collect($this->getExif())->pipe(function ($c) {
            return collect($c->get('IFD0'));
        })->get('Orientation', 1);
        $rotate = collect([
            3 => 180,
            6 => -90,
            8 => 90,
        ])->get($oren, 0);
        $thumb = $rotate != 0 ? imagerotate($thumb, $rotate, 0) : $thumb;
        if (in_array($oren, [6, 8])) {
            list($new_width, $new_height) = [$new_height, $new_width];
        }
        return [
            'thumb' => $thumb,
            'new_width' => $new_width,
            'new_height' => $new_height,
        ];
    }

    public function save($filename = null) {
        if (!$this->validation()) {
            return false;
        }
        $filename = $this->getNameWithExtension($filename);
        $rsc = $this->resize();
        $host_path = join(DIRECTORY_SEPARATOR, [$this->file_dir, $filename]);
        if (!array_key_exists($this->getType(), self::_HANDLERS)) {
            return false;
        }
        switch ($this->getType()) {
        case IMAGETYPE_JPEG:
            $quality = $this->quality_jpg;
            break;
        case IMAGETYPE_PNG:
            $quality = $this->quality_png;
            break;
        default:
            $quality = null;
            break;
        }
        $rst = call_user_func(self::_HANDLERS[$this->getType()]['save'], $rsc['thumb'], $host_path, $quality);
        return !$rst ? false : [
            'original' => $this->docName(),
            'filename' => $filename,
            'width' => $rsc['new_width'],
            'height' => $rsc['new_height'],
        ];
    }
    public function upload($target_name = null) {
        return parent::upload($this->getNameWithExtension($target_name));
    }
    public static function getInfoVal($val, array $map) {
        return array_key_exists($val, $map) ? $map[$val] : 'Unknown';
    }
    public static function getImageInfo(array $exif) {
        $imgtype = ["", "GIF", "JPG", "PNG", "SWF", "PSD", "BMP", "TIFF(intel byte order)", "TIFF(motorola byte order)", "JPC", "JP2", "JPX", "JB2", "SWC", "IFF", "WBMP", "XBM"];
        $Orientation = ["", "Horizontal (normal)", "Mirror horizontal", "Rotate 180", "Mirror vertical", "Mirror horizontal and rotate 270 CW", "Rotate 90 CW", "Mirror horizontal and rotate 90 CW", "Rotate 270 CW"];
        $ResolutionUnit = ["", "None", "inches", "cm"];
        $YCbCrPositioning = ["", "Centered", "Co-sited"];
        $ExposureProgram = ["未定義", "手動", "標準程式", "光圈先決", "快門先決", "景深先決", "運動模式", "肖像模式", "風景模式"];
        $MeteringMode_arr = ["0" => "Unknown", "1" => "平均測光", "2" => "中央重點平均測光", "3" => "點測光", "4" => "多點測光", "5" => "多區測光", "6" => "部份測光", "255" => "其他"];
        $Lightsource_arr = ["0" => "Unknown", "1" => "日光", "2" => "熒光燈", "3" => "鎢絲燈", "10" => "閃光燈", "17" => "標準燈光A", "18" => "標準燈光B", "19" => "標準燈光C", "20" => "D55", "21" => "D65", "22" => "D75", "255" => "其他"];
        $Flash_arr = ["0" => "No Flash", "1" => "Fired", "5" => "Fired, Return not detected", "7" => "Fired, Return detected"];
        $ob = collect($exif);
        $ifd = collect($ob->get('IFD0', false));
        if ($ifd === false) {
            return ["檔案資訊" => "沒有圖片EXIF資訊"];
        }
        $file = collect($ob->get('FILE'));
        $computed = collect($ob->get('COMPUTED'));
        $exif = collect($ob->get('EXIF'));
        return [
            /* "檔名" => $file->get('FileName', ''),
            "檔案型別" => self::getInfoVal($file->get('FileType', 0), $imgtype),
            "檔案格式" => $file->get('MimeType', ''),
            "檔案大小" => $file->get('FileSize', ''),
            "時間戳" => date("Y-m-d H:i:s", $file->get('FileDateTime', '')), */
            //"影像資訊" => '',
            "圖片說明" => $ifd->get('ImageDescription', ''),
            "製造商" => $ifd->get('Make', ''),
            "型號" => $ifd->get('Model', ''),
            "方向" => self::getInfoVal($ifd->get('Orientation'), $Orientation),
            "水平解析度" => $ifd->get('XResolution', 0) . self::getInfoVal($ifd->get('ResolutionUnit'), $ResolutionUnit),
            "垂直解析度" => $ifd->get('YResolution', 0) . self::getInfoVal($ifd->get('ResolutionUnit'), $ResolutionUnit),
            "建立軟體" => $ifd->get('Software', ''),
            "修改時間" => $ifd->get('DateTime', ''),
            "作者" => $ifd->get('Artist', ''),
            "YCbCr位置控制" => self::getInfoVal($ifd->get('YCbCrPositioning'), $YCbCrPositioning),
            "版權" => $ifd->get('Copyright', ''),
            "攝影版權" => $computed->get('Copyright.Photographer', ''),
            "編輯版權" => $computed->get('Copyright.Editor', ''),
            //"拍攝資訊" => '',
            "Exif版本" => $exif->get('ExifVersion', ''),
            "FlashPix版本" => "Ver. " . number_format($exif->get('FlashPixVersion', 100) / 100, 2),
            "拍攝時間" => $exif->get('DateTimeOriginal', ''),
            "數字化時間" => $exif->get('DateTimeDigitized', ''),
            "拍攝解析度寬" => $computed->get('Width', ''),
            "拍攝解析度高" => $computed->get('Height', ''),
            /* "光圈" => $exif->get('ApertureValue', ''),
            "快門速度" => $exif->get('ShutterSpeedValue', ''), */
            "光圈" => $computed->get('ApertureFNumber', ''),
            "最大光圈值" => "F" . $exif->get('MaxApertureValue', ''),
            "曝光時間" => $exif->get('ExposureTime', ''),
            "F-Number" => $exif->get('FNumber', ''),
            "測光模式" => self::getInfoVal($exif->get('MeteringMode'), $MeteringMode_arr),
            "光源" => self::getInfoVal($exif->get('LightSource'), $Lightsource_arr),
            "閃光燈" => self::getInfoVal($exif->get('Flash'), $Flash_arr),
            "曝光模式" => $exif->get('ExposureMode') == 1 ? "手動" : "自動",
            "白平衡" => $exif->get('WhiteBalance') == 1 ? "手動" : "自動",
            "曝光程式" => self::getInfoVal($exif->get('ExposureProgram'), $ExposureProgram),
            "曝光補償" => $exif->get('ExposureBiasValue', '0') . "EV",
            "ISO感光度" => $exif->get('ISOSpeedRatings', ''),
            "分量配置" => (bin2hex($exif->get('ComponentsConfiguration')) == "01020300" ? "YCbCr" : "RGB"),
            "影像壓縮率" => $exif->get('CompressedBitsPerPixel', '0') . "Bits/Pixel",
            "對焦距離" => $computed->get('FocusDistance', '0') . "m",
            "焦距" => $exif->get('FocalLength', '0') . "mm",
            "等價35mm焦距" => $exif->get('FocalLengthIn35mmFilm', '0') . "mm",
            "使用者註釋編碼" => $computed->get('UserCommentEncoding', ''),
            "使用者註釋" => $computed->get('UserComment', ''),
            "色彩空間" => $exif->get('ColorSpace') == 1 ? "sRGB" : "Uncalibrated",
            "Exif影像寬度" => $exif->get('ExifImageWidth', ''),
            "Exif影像高度" => $exif->get('ExifImageLength', ''),
            "檔案來源" => bin2hex($exif->get('FileSource')) == 0x03 ? "Digital Camera" : "Unknown",
            "場景型別" => bin2hex($exif->get('SceneType')) == 0x01 ? "Directly photographed" : "Unknown",
            "縮圖檔案格式" => $computed->get('Thumbnail.FileType', ''),
            "縮圖Mime格式" => $computed->get('Thumbnail.MimeType', ''),
            "機身序號" => $exif->get('UndefinedTag:0xA431', ''),
            "鏡頭模組" => $exif->get('UndefinedTag:0xA434', ''),
        ];
    }
}
?>