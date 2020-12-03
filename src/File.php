<?php
namespace Exinfinite\ImgUpload;

class File {
    protected $mime = [];
    protected $size_limit = '30M';
    protected static $units = [
        'b' => 1,
        'k' => 1024,
        'm' => 1048576,
        'g' => 1073741824,
    ];
    protected $extension = [
        'image/jpg' => 'jpg',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    public function __construct($input_key, $file_dir) {
        if (!isset($_FILES[$input_key])) {
            throw new \InvalidArgumentException("Cannot find uploaded file identified by key: $input_key");
        }
        $this->file_dir = $file_dir;
        $this->file = $_FILES[$input_key];
        $this->mkdir($this->file_dir);
    }
    public static function humanReadableToBytes($input) {
        $number = (int) $input;
        $unit = strtolower(substr($input, -1));
        return isset(self::$units[$unit]) ?
        $number * self::$units[$unit] :
        $number;
    }
    public function mkdir($dir) {
        if (!file_exists($dir) && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    public function isUploadedFile() {
        return is_uploaded_file($this->getFile());
    }
    public function fileError() {
        throw new \Exception("檔案格式或容量大小錯誤");
        exit;
    }
    public function upload($target_name) {
        if (!$this->validation()) {
            $this->fileError();
        }
        ini_set('upload_max_filesize', $this->size_limit);
        if (move_uploaded_file($this->getFile(), join(DIRECTORY_SEPARATOR, [$this->file_dir, $target_name]))) {
            return $target_name;
        }
        return false;
    }
    public function getMimeType() {
        return $this->file['type'];
    }
    public function getSize() {
        return $this->file['size'];
    }
    public function getFile() {
        return $this->file['tmp_name'];
    }
    public function docName($withExt = false) {
        return $withExt ? $this->file['name'] :
        substr($this->file['name'], 0, strrpos($this->file['name'], '.'));
    }
    public function getNameWithExtension($filename = null) {
        $filename = isset($filename) ? $filename : uniqid(date('ymdhis') . '_');
        if (array_key_exists($this->getMimeType(), $this->extension)) {
            return sprintf('%s.%s', $filename, $this->extension[$this->getMimeType()]);
        }
        $this->fileError();
    }
    public function setValidMime(array $mime = []) {
        $this->mime = $mime;
    }
    public function sizeLimit($size) {
        $this->size_limit = $size;
    }
    protected function mimeValidation() {
        return in_array($this->getMimeType(), $this->mime);
    }
    protected function sizeValidation() {
        return ($this->getSize() <= self::humanReadableToBytes($this->size_limit));
    }
    public function validation() {
        return ($this->isUploadedFile() && $this->mimeValidation() && $this->sizeValidation());
    }
    public function md5Hash() {
        return hash_file('md5', $this->getFile());
    }
}
?>