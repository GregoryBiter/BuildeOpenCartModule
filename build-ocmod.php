<?php
$consolePath = getenv('PWD');

// Если 'PWD' не задан, используем 'getcwd'
if ($consolePath === false) {
    $consolePath = getcwd();
}
define("WORK_CATALOG", $consolePath);
define("FILE_TXT_BUILD", WORK_CATALOG."/build/build-ocmod.txt");
define("ZIP_PATH_BUILD",  WORK_CATALOG."/build/");
define("OCMOD_FILE", WORK_CATALOG."/build/install.xml");
define("OCMOD_ZIP_FILE", WORK_CATALOG."/install.xml");
define("BASE_DIR_BUILD_UPLOAD", WORK_CATALOG. "/build/upload");
define("BASE_DIR_BUILD_ZIP", WORK_CATALOG."/build");


class File extends Factory
{
    public function copyFiles($source, $destination)
    {
        $this->createDirectoryIfNotExists(dirname($destination));
        if (copy($source, $destination)) {
            echo "$source скопирован в:\n$destination\n\n";
        } else {
            echo "Не удалось скопировать $source в $destination\n\n";
        }
    }


    public function createDirectoryIfNotExists($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }


    public function copyFilesWithPattern($pattern, $baseDir, $sourse = WORK_CATALOG, $des = null)
    {
        $items = glob($pattern, GLOB_BRACE);
        foreach ($items as $item) {
            $relativePath = ltrim(str_replace($sourse, '', $item), '/');
            $destination = $baseDir . '/' . $relativePath;
            if (is_dir($item)) {
                // Рекурсивно копируем содержимое директории
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($item, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $subItem) {
                    $subRelativePath = ltrim(str_replace($sourse, '', $subItem), '/');
                    $subDestination = $baseDir . '/' . $subRelativePath;

                    if ($subItem->isDir()) {
                        $this->createDirectoryIfNotExists($subDestination);
                    } else {
                        $this->copyFiles($subItem, $subDestination);
                    }
                }
            } else {
                $this->copyFiles($item, $destination);
            }
        }
    }

    public function deleteListFile($fileList)
    {
        foreach ($fileList as $file) {
            $file = ltrim($file, '/\\');
            $fullPath = WORK_CATALOG . '/' . $file;
            if (strpos($file, '*') !== false) {
                $this->deleteFilesWithPattern($fullPath);
            } else {
                $this->deleteFiles($fullPath);
            }
        }
    }

    public function deleteFilesWithPattern($pattern)
    {
        $items = glob($pattern, GLOB_BRACE);
        foreach ($items as $item) {
            if (is_dir($item)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($item, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $subItem) {
                    if ($subItem->isDir()) {
                        rmdir($subItem);
                        echo "Пустая директория {$subItem} удалена\n";
                    } else {
                        unlink($subItem);
                        echo "Файл {$subItem} удалён\n";
                    }
                }
                rmdir($item);
                echo "Пустая директория {$item} удалена\n";
            } else {
                unlink($item);
                echo "Файл {$item} удалён\n";
            }
        }
    }


    public function deleteFiles($file)
    {
        if (file_exists($file)) {
            unlink($file);
            echo "Файл $file удалён\n";
        } else {
            echo "Файл $file не найден\n";
        }
        // Удаляем пустые родительские директории
        $dir = dirname($file);
        while ($dir !== WORK_CATALOG && is_dir($dir) && count(glob("$dir/*")) === 0) {
            rmdir($dir);
            echo "Пустая директория $dir удалена\n";
            $dir = dirname($dir);
        }
    }
}

class Console extends Factory
{
    // protected $registry;
    // public function __construct(Registry $registry)
    // {
    //     $this->registry = $registry;
    // }
    public function getInput($prompt)
    {
        echo $prompt;
        $input = trim(fgets(STDIN));
        return $input;
    }
    public function commands($argv)
    {
        $commands = [
            'install',
            'uninstall',
            'build',
            'init',
        ];
		$this->version();
        if (count($argv) < 2 || !in_array($argv[1], $commands)) {
            echo "Команда " . ($argv[1] ?? '') . " не найдена\n";
            return;
        }
		
        $this->{$argv[1]}($argv);
        // call_user_func($argv[1], $argv);
    }
	public function version(){
		echo "Ocmod Build (GbitStudio) v1.3". PHP_EOL;
	}
    public function uninstall($argv)
    {
        echo "Start Uninstall\n";

        $fileList = $this->Ocbuild->readBuildFile(FILE_TXT_BUILD);
        $nameModule = $this->Ocbuild->getModuleName($fileList);
        $this->File->deleteListFile($fileList);
        if (is_file(WORK_CATALOG . "/" . OCMOD_FILE) && is_file($this->Ocbuild->getNameOcmod($nameModule))) {
            $this->File->deleteFiles($this->Ocbuild->getNameOcmod($nameModule));
            //deleteFiles(getNameOcmod($nameModule));
        }
    }

    public function install($argv)
    {
        echo "Start Install";
        $fileList = $this->Ocbuild->readBuildFile(FILE_TXT_BUILD);
        $nameModule = $this->Ocbuild->getModuleName($fileList);
        foreach ($fileList as $file) {
            $file = ltrim($file, '/\\');
            $fullPath = BASE_DIR_BUILD_UPLOAD . '/' . $file;
            //$fullPath = WORK_CATALOG . '/' . $file;
            if (strpos($file, '*') !== false) {
                // Если путь содержит подстановочный знак, используем glob для копирования соответствующих файлов и папок
                $this->File->copyFilesWithPattern($fullPath, WORK_CATALOG, BASE_DIR_BUILD_UPLOAD);
            } else {
                $destination = WORK_CATALOG . '/' . $file;
                $this->File->copyFiles($fullPath, $destination);
            }
        }
        if (is_file(WORK_CATALOG . "/" . OCMOD_FILE)) {
            $this->File->copyFiles(WORK_CATALOG . "/" . OCMOD_FILE, WORK_CATALOG . "/system/" . strtolower($nameModule) . ".ocmod.xml");
        }
    }

    public function init($argv)
    {
        if (is_file(WORK_CATALOG . "/" . FILE_TXT_BUILD)) {
            echo "Модуль вже існує. Якщо ви хочете створити новий. Видаліть всі файли із папки: " . BASE_DIR_BUILD_UPLOAD;
            exit;
        }
        $nameModule = $this->getInput('Введіть ім\'я модуля: ');
        $data = "# " . $nameModule . PHP_EOL;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(BASE_DIR_BUILD_UPLOAD, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $name => $file) {
            // Пропускаем директории (они автоматически добавляются)
            if (!$file->isDir()) {
                // Получаем реальный путь и относительный путь
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(BASE_DIR_BUILD_UPLOAD) + 1);
                $data .= $relativePath . PHP_EOL;
                //echo "Copy: " . $relativePath . PHP_EOL;

                //$zip->addFile($filePath, $relativePath);
            }
        }
        $file = FILE_TXT_BUILD;
        if (isset($argv[2])) {
            $file = $argv[2];
        }
        file_put_contents($file, $data);
    }

    function build()
    {
        // Создаем папку build/upload, если она не существует
        $this->File->createDirectoryIfNotExists(BASE_DIR_BUILD_UPLOAD);

        $fileList = $this->Ocbuild->readBuildFile(FILE_TXT_BUILD);

        $nameModule = $this->Ocbuild->getModuleName($fileList);

        foreach ($fileList as $file) {
            $file = ltrim($file, '/');
            $fullPath = WORK_CATALOG . '/' . $file;
            if (strpos($file, '*') !== false) {
                // Если путь содержит подстановочный знак, используем glob для копирования соответствующих файлов и папок
                $this->File->copyFilesWithPattern($fullPath, BASE_DIR_BUILD_UPLOAD);
            } else {
                $destination = BASE_DIR_BUILD_UPLOAD . '/' . $file;
                $this->File->copyFiles($fullPath, $destination);
            }
        }
        $this->Ocbuild->createZip(ZIP_PATH_BUILD . $nameModule . ".zip");
    }
}

final class Registry
{
    private $data = array();
    public function get($key)
    {
        return (isset($this->data[$key]) ? $this->data[$key] : null);
    }
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }
}
abstract class Factory
{
    protected $registry;
    public function __construct($registry)
    {
        $this->registry = $registry;
    }
    public function __get($key)
    {
        return $this->registry->get($key);
    }

    public function __set($key, $value)
    {
        $this->registry->set($key, $value);
    }
}

class OpencartBuild extends Factory
{
    public function getNameOcmod($nameModule)
    {
        return WORK_CATALOG . "/system/" . strtolower($nameModule) . ".ocmod.xml";
    }

    function readBuildFile($file)
    {
        $fileList = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$fileList) {
            echo "Файл " . $file . " не найден или пуст\n";
            exit;
        }
        return $fileList;
    }

    public function getModuleName(&$fileList)
    {
        if (strpos($fileList[0], "#") == 0) {
            $name = str_replace('#', '', $fileList[0]);
            $name = ltrim($name);
            $name = str_replace(' ', '_', $name);
            unset($fileList[0]);
        } else {
            $name = 'default_module';
        }
        return $name;
    }

    public function createZip($nameZip)
    {
        // Создаем zip архив
        $zip = new ZipArchive();

        if ($zip->open($nameZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(BASE_DIR_BUILD_UPLOAD, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $name => $file) {
                // Пропускаем директории (они автоматически добавляются)
                if (!$file->isDir()) {
                    // Получаем реальный путь и относительный путь
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen(BASE_DIR_BUILD_ZIP) + 1);

                    echo "Copy: " . $relativePath . PHP_EOL;
                    // Добавляем файл в архив
                    $zip->addFile($filePath, $relativePath);
                }
            }
            // Добавляем файл FILE_TXT в корень архива
            $zip->addFile(FILE_TXT_BUILD, basename(FILE_TXT_BUILD));
            if (is_file(OCMOD_FILE)) {
                $zip->addFile(OCMOD_FILE, basename(OCMOD_FILE));
            }

            // Закрываем архив
            $zip->close();
            echo "Архив успешно создан: $nameZip\n";
        } else {
            echo "Не удалось создать архив: $nameZip\n";
        }
    }
}
$registry = new Registry;
$file = new File($registry);
$buildHelper = new OpencartBuild($registry);
$registry->set('File', $file);
$registry->set('Ocbuild', $buildHelper);
$console = new Console($registry);
$console->commands($argv);
