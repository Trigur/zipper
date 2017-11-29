<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * @property Zipper_model zipper_model
 */
class Zipper extends MY_Controller
{
    private $files;
    private $distBaseFolder = '/uploads/zip/';
    private $documentRoot;

    public function __construct() {
        parent::__construct();
        $this->documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $this->load->model('zipper_model');
    }

    /*
        Старт
    */
    public function _toZip($files = array(), $name = null, $addDate = true) {
        if (empty($files)) return false;

        if (strlen($name) > 50) {
            $name = substr($name, 0, 50);
        }

        try {
            $this->files = $files;

            $this->_prepareFiles();

            $hash = $this->_getHash();

            $distFromDb = $this->zipper_model->getByHash($hash);

            // Если файлы уже были заархивированы - возвращаем путь
            if ($distFromDb && file_exists($this->documentRoot . $distFromDb['relativePath'])) {
                return $distFromDb['relativePath'];
            } else {
                // Архив пропал - удаляем запись
                $this->zipper_model->remove($distFromDb['id']);
            }


            // Файлы еще не архивировались
            $destination = $this->_getDestination($name, $addDate);

            $distData = [
	            'hash'         => $hash,
	            'name'         => $name ?: '',
	            'relativePath' => $destination,
            ];

            if (! $this->_makeArchive($this->documentRoot . $destination)) {
                throw new Exception( 'Архив не создан', 1);
            }

            $this->zipper_model->insert($distData);

            return $distData['relativePath'];
        } catch (Exception $e) {
            if (ENVIRONMENT == 'development'){
                exit($e->getMessage());
            }
            return false;
        }
    }

    /*
        Создаем архив
    */
    private function _makeArchive($destination)
    {
        $zip = new ZipArchive();
        if ( $zip->open($destination, false ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
            throw new Exception( 'Ошибка создания архива', 1);
        }

        foreach ($this->files as $file) {
            $zip->addFile($file['absolutePath'], $file['name']);
        }

        $zip->close();

        return file_exists($destination);
    }

    /*
        Хэшируем названия файлов для проверки существования архива
    */
    private function _getHash() {
        $hash = '';
        foreach ($this->files as $item) {
            $hash .= $item['relativePath'];
        }

        $hash = md5($hash);

        return $hash;
    }

    /*
        Создаем папку для нашего архива.
        Возвращаем относительный путь до конечного файла.
    */
    public function _getDestination($name = null, $addDate = false) {
        $time = time();

        if (empty($name)) {
            $name .= date('dmY');
            $addDate = false;
        }

        $fileName = $name;

        if ($addDate) {
            $fileName .= '_' . date('dmY');
        }

        $fileName .= '.zip';

        $distPath = $this->distBaseFolder . date('d.m.Y') . '/';

        if (file_exists($this->documentRoot . $distPath . $fileName)) {
            return $this->_getDestination(random_int(1, 9) . '_' . $name, $addDate);
        }

        if (! file_exists($this->documentRoot . $distPath)) {
            if (! @mkdir($this->documentRoot . $distPath, 0777, $recursive = true)){
                throw new Exception( 'Ошибка создания папки для архива', 1);
            }
        }

        return $distPath . $fileName;
    }

    /*
        Убираем несуществующие файлы.
    */
    private function _prepareFiles() {
        if (empty($this->files)) {
            throw new Exception( 'Не найдены файлы для добавления в архив', 1);
        }

        if (! is_array($this->files)) {
            $this->files = [$this->files];
        }

        $files = [];
        foreach ($this->files as $filePath) {
            $name = explode('/', $filePath);
            $name = $name[count($name) - 1];

            $relativePath = str_replace($this->documentRoot, '', $filePath);
            $relativePath = '/' . ltrim($relativePath, '/');

            $absolutePath = $this->documentRoot . $relativePath;

            if (file_exists($absolutePath)) {
                $files[] = [
                    'name'         => $name,
                    'relativePath' => $relativePath,
                    'absolutePath' => $absolutePath,
                ];
            }
        }

        if (empty($files)) {
            throw new Exception( 'Не найдены файлы для добавления в архив', 1);
        }

        $this->files = $files;
    }

    /*
        Установка модуля
    */
    public function _install()
    {
        if (! extension_loaded('zip')) {
            $this->zipper_model->errorInstall();

            exit(json_encode([
                'result' => false,
                'error'  => true,
                'message' => 'Отсутствует расширение zip для php.'
            ]));
        }

        $this->zipper_model->install();
    }

    /*
        Удаление модуля
    */
    public function _deinstall()
    {
        $this->zipper_model->deinstall();
    }
}
