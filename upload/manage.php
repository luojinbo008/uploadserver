<?php
/**
 * 图片按比例裁剪
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 16-8-16
 * Time: 上午11:37
 */
ini_set('date.timezone', 'Asia/Shanghai');
include '../vendor/autoload.php';
class manageService
{
    protected $request = null;
    //允许的类型
    protected $allowExtens = [
        'jpg', 'jpeg', 'gif', 'bmp', 'png', 'rar', 'zip', 'pdf', 'xls', 'ppt', 'jpe'
    ];
    //文件最大的大小,10M
    protected $maxSize = 2097152;

    //文件mime
    protected $mime = [
        'bmp' => ['image/bmp', 'image/x-windows-bmp'],
        'gif' => 'image/gif',
        'jpeg'=> ['image/jpeg', 'image/pjpeg'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpe' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png',  'image/x-png'],
        'pdf' => ['application/pdf', 'application/x-download'],
        'xls' => ['application/excel', 'application/vnd.ms-excel', 'application/msexcel'],
        'ppt' => ['application/powerpoint', 'application/vnd.ms-powerpoint'],
        'rar' => 'application/x-rar-compressed',
        'zip' => ['application/x-zip', 'application/zip', 'application/x-zip-compressed'],
    ];

    protected $originPrefix = 'upload_service';
    //上传
    protected $origin = [
        'upload_service_main',
    ];

    public function __construct(\Phalcon\Http\Request $request)
    {
        $this->request = $request;
    }

    public function upload()
    {
        if (!$this->request->isPost()) {
            $this->showMessage('对不起，请求方式不正确');
        }
        $getOrigin = $this->request->getPost('origin');
        if (empty($getOrigin) || !in_array($getOrigin, $this->origin)) {
            $this->showMessage('对不起，来源不正确!');
        }
        $module = $this->request->getPost('module');
        if (empty($module) || preg_match_all('/\W/', $module) > 0 || strlen($module) > 20) {
            $this->showMessage('对不起，模块名不正确!');
        }
        $appid = (int)$this->request->getPost('appid');
        if (empty($appid)) {
            $this->showMessage('对不起，appid不正确!!');
        }
        $directory = $this->request->getPost('directory') ? $this->request->getPost('directory') : false;

        if (!$this->request->hasFiles()) {
            $this->showMessage('对不起，没有可上传的文件!');
        }
        $uploadFile = $this->request->getUploadedFiles(); // 获取上传的文件
        $filename = [];
        $resourcePath = $this->positionPath();
        $time = time();
        if ($directory) {
            $filePath = $module . '/' . $appid . '/' . $directory;
        } else {
            $ymd = date('Ymd', $time);
            $filePath = $module . '/' . $appid . "/" . $ymd;
        }

        foreach ($uploadFile as $key => $value) {
            $extenName = pathinfo($value->getName(), PATHINFO_EXTENSION);
            if (!in_array(strtolower($extenName), $this->allowExtens)) {
                $this->showMessage('对不起, 文件类型不支持!');
            }
            $fileSize = $value->getSize();
            if ($fileSize > $this->maxSize) {
                $this->showMessage('对不起, 超出文件上传大小!');
            }
            $type = $value->getRealType();
            $checkMime = $this->mime[$extenName];
            if ((is_array($checkMime) && !in_array($type, $checkMime)) || (is_string($checkMime) && $type != $checkMime)) {
                $this->showMessage('对不起, 文件不合法!');
            }
            if (!file_exists($resourcePath . $filePath)) {
                mkdir($resourcePath . $filePath, 0777, true);
            }
            $name = md5($value->getName() . $time . mt_rand(1, 100)) . '.' . $extenName;
            $filename[$key] = $filePath . '/' . $name;
            $value->moveto($resourcePath . '/' . $filename[$key]);
        }
        $this->showMessage('文件上传成功!', 0, $filename);
    }

    public function delFile()
    {
        $request = $this->request->get();
        $resourcePath = $this->positionPath();
        $appid = (int) $request['appid'];
        $json = [];
        if (isset($request['path'])) {
            $paths = $request['path'];
        } else {
            $paths = [];
        }
        foreach ($paths as $path) {
            $path = rtrim($resourcePath . 'catalog/' . $appid . '/'. str_replace(array('../', '..\\', '..'), '', $path), '/');
            if ($path == $resourcePath . 'catalog/' . $appid) {
                $json['error'] = '警告: 不能删除此目录！';
                break;
            }
        }
        if (!$json) {
            foreach ($paths as $path) {
                $path = rtrim($resourcePath . 'catalog/' . $appid . '/' . str_replace(array('../', '..\\', '..'), '', $path), '/');
                if (is_file($path)) {
                    unlink($path);
                } elseif (is_dir($path)) {
                    $files = array();
                    $path = array($path . '*');
                    while (count($path) != 0) {
                        $next = array_shift($path);
                        foreach (glob($next) as $file) {
                            if (is_dir($file)) {
                                $path[] = $file . '/*';
                            }
                            $files[] = $file;
                        }
                    }
                    rsort($files);
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        } elseif (is_dir($file)) {
                            rmdir($file);
                        }
                    }
                }
            }
            $json['success'] = "成功: 文件或目录已经被删除！";
        }
        $this->showMessage('success', 0, $json);
    }

    /**
     * 裁剪图片
     */
    public function resize()
    {
        $filename = $this->request->get('image');
        $width = $this->request->get('width');
        $height = $this->request->get('height');
        $new_image = $this->resizeImage($filename, $width, $height);
        $this->showMessage('文件裁剪成功!', 0, $new_image);
    }

    /**
     * 创建文件夹
     */
    public function createFile()
    {
        $resourcePath = $this->positionPath();
        $request = $this->request->get();
        $appid = (int) $request['appid'];
        $json = [];
        if (isset($request['directory'])) {
            $directory = rtrim($resourcePath . 'catalog/' . $appid . '/' . str_replace(array('../', '..\\', '..'), '', $request['directory']), '/');
        } else {
            $directory = $resourcePath . 'catalog/' . $appid;
        }
        if (!is_dir($directory)) {
            $json['error'] = "警告: 目录不存在！";
        }
        if (!$json) {
            $folder = str_replace(array('../', '..\\', '..'), '', basename(html_entity_decode($request['folder'], ENT_QUOTES, 'UTF-8')));
            if ((mb_strlen($folder) < 3) || (mb_strlen($folder) > 128)) {
                $json['error'] = "警告: 文件夹名称必须介于3到255字符之间！";
            }
            if (is_dir($directory . '/' . $folder)) {
                $json['error'] = '警告: 已经存在同名文件夹或文件！';
            }
        }
        if (!$json) {
            mkdir($directory . '/' . $folder, 0777);
            chmod($directory . '/' . $folder, 0777);
            $json['success'] = '成功: 目录已经创建！';
        }
        $this->showMessage('success', 0, $json);
    }

    /**
     * 文件管理
     */
    public function fileManage()
    {
        $resourcePath = $this->positionPath();
        $request = $this->request->get();
        $appid = (int) $request['appid'];
        if (isset($request['filter_name'])) {
            $filter_name = rtrim(str_replace(array('../', '..\\', '..', '*'), '', $request['filter_name']), '/');
        } else {
            $filter_name = null;
        }
        if (isset($request['directory'])) {
            $directory = rtrim($resourcePath . 'catalog/' . $appid . '/' . str_replace(array('../', '..\\', '..'), '', $request['directory']), '/');
        } else {
            $directory = $resourcePath . 'catalog/' . $appid;
        }
        if (isset($request['page'])) {
            $page = $request['page'];
        } else {
            $page = 1;
        }
        if (isset($request['size'])) {
            $size = $request['size'];
        } else {
            $size = 16;
        }

        $data['images'] = [];
        $directories = glob($directory . '/' . $filter_name . '*', GLOB_ONLYDIR);

        if (!$directories) {
            $directories = [];
        }
        $files = glob($directory . '/' . $filter_name . '*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}', GLOB_BRACE);
        if (!$files) {
            $files = [];
        }
        $images = array_merge($directories, $files);
        $data['image_total'] = count($images);
        $images = array_splice($images, ($page - 1) * $size, $size);
        foreach ($images as $image) {
            $name = str_split(basename($image), 14);
            if (is_dir($image)) {
                $data['images'][] = array(
                    'thumb' => '',
                    'name'  => implode(' ', $name),
                    'type'  => 'directory',
                    'path'  => mb_substr($image, mb_strlen($resourcePath . 'catalog/' . $appid . '/')),
                );
            } elseif (is_file($image)) {
                $data['images'][] = array(
                    'thumb' => $this->resizeImagePercent(mb_substr($image, mb_strlen($resourcePath)), 30),
                    'name'  => implode(' ', $name),
                    'type'  => 'image',
                    'path'  => mb_substr($image, mb_strlen($resourcePath)),
                );
            }
        }
        $this->showMessage('获得文件列表成功！', 0, $data);
    }

    /**
     * 裁剪图片
     * @param $filename
     * @param $width
     * @param $height
     */
    protected function resizeImage($filename, $width, $height)
    {
        $resourcePath = $this->positionPath();
        if (!is_file($resourcePath . $filename) || !$width || !$height) {
            $filename = "catalog/no_image.png";
        }
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $old_image = $filename;
        $new_image = 'cache/' . mb_substr($filename, 0, mb_strrpos($filename, '.')) .
            '-' . $width . 'x' . $height . '.' . $extension;

        if (!is_file($resourcePath . $new_image) || (filectime($resourcePath . $old_image)
                > filectime($resourcePath . $new_image))) {
            $path = '';
            $directories = explode('/', dirname(str_replace('../', '', $new_image)));
            foreach ($directories as $directory) {
                $path = $path . '/' . $directory;
                if (!is_dir($resourcePath . $path)) {
                    @mkdir($resourcePath . $path, 0777);
                }
            }
            $thumb = new PHPThumb\GD($resourcePath  . $old_image);
            $thumb->adaptiveResize($width, $height);
            $thumb->save($resourcePath . $new_image, $extension);
        }
        return $new_image;
    }

    /**
     * 按百分比 裁剪图片
     * @param $filename
     * @param int $percent
     * @return string
     */
    protected function resizeImagePercent($filename, $percent = 50)
    {
        $resourcePath = $this->positionPath();
        if (!is_file($resourcePath . $filename) || !$percent) {
            $filename = "catalog/no_image.png";
        }
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $old_image = $filename;
        $new_image = 'cache/' . mb_substr($filename, 0, mb_strrpos($filename, '.')) .
            '-' . $percent . 'percent' . '.' . $extension;

        if (!is_file($resourcePath . $new_image) || (filectime($resourcePath . $old_image)
                > filectime($resourcePath . $new_image))) {
            $path = '';
            $directories = explode('/', dirname(str_replace('../', '', $new_image)));
            foreach ($directories as $directory) {
                $path = $path . '/' . $directory;
                if (!is_dir($resourcePath . $path)) {
                    @mkdir($resourcePath . $path, 0777);
                }
            }
            $thumb = new PHPThumb\GD($resourcePath  . $old_image);
            $thumb->resizePercent($percent);
            $thumb->save($resourcePath . $new_image, $extension);
        }
        return $new_image;
    }

    protected function showMessage($msg, $status = 1, $data = array())
    {
        $message = [
            'message' => $msg,
            'status'  => $status,
            'data' => $data,
        ];
        echo json_encode($message);die;
    }

    protected function positionPath()
    {
        return rtrim(dirname(dirname(__DIR__)), '\\/') . '/resource/images/';
    }
}
$request = new \Phalcon\Http\Request();
$service = new manageService($request);
$type = $request->get('type');
switch ($type) {
    case 'resize':
        $service->resize();
        break;
    case 'manage':
        $service->fileManage();
        break;
    case 'createFile' :
        $service->createFile();
        break;
    case 'delFile':
        $service->delFile();
        break;
    case 'upload':
        $service->upload();
        break;
}