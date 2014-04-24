<?php

/**
 * Representa la interfaz común de todos los compresores de archivos
 */
abstract class Archive_Base
{

    protected $_data = array();

    /**
     * Nivel de compresión de 0 a 100, donde 100 es compresión máxima
     * @var int
     */
    public $level = 75;

    /**
     * Mensajes de adventencia o error obtenidos durante la creación del fichero
     * @var string[]
     */
    public $log;

    public function __construct()
    {
        $this->clear();
    }

    /**
     * Limpia el archivo actual, comenzando uno nuevo
     */
    public function clear()
    {
        $this->_data = array();
        $this->log = array();
    }

    /**
     * Añade un fichero(s) al archivo dada su ruta
     *
     * @param string|string[] $path
     * @param string          $local_name Nombre que tendrá el archivo en el fichero creado
     */
    public function add_file($path, $local_name = null)
    {
        if (is_array($path)) {
            foreach ($path as $p) {
                $this->_data[] = array(
                    'path' => $path,
                    'name' => $path
                );
            }
        } else {
            $this->_data[] = array(
                'path' => $path,
                'name' => isset($local_name) ? $local_name : $path
            );
        }
    }

    /**
     * Añade un fichero al archivo dado su contenido
     */
    public function add_data($local_name, $content)
    {

        $this->_data[] = array(
            'content' => $content,
            'name' => $local_name
        );
    }

    /**
     * Añade un directorio al archivo
     */
    public function add_folder($local_name)
    {
        $this->_data[] = array(
            'is_dir' => true,
            'content' => '',
            'name' => $local_name
        );
    }

    /**
     * Prepara la lista y jerarquía de los archivos a comprimir
     * @return Archive_File[]
     */
    protected function _get_files()
    {
        $files = array();

        //Incluir archivos e información sobre ellos
        foreach ($this->_data as $info) {
            //Comprobar si el archivo ya existe
            $found = false;
            foreach ($files as $f) {
                if ($f == $info['name']) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                continue;
            }

            //Crear descriptor de archivo
            $file = new Archive_File();
            $file->path = $info['name'];

            if (isset($info['content'])) {
                $file->content = $info['content'];
                $file->is_directory = isset($info['is_dir']) && $info['is_dir'];
            } else {
                $file->real_path = realpath($info['path']);

                if (!$file->real_path) {
                    $this->log[] = "File {$info['path']} not found";
                    continue;
                }

                $file->is_directory = is_dir($file->real_path);
            }

            $files[] = $file;
        }

        //Ordenar archivos (según carpeta, tipo, etc.)
        if (!function_exists('_archive_sort_files')):

            function _archive_sort_files(Archive_File $a, Archive_File $b)
            {
                //Order por carpetas, extensiones y nombre
                $a_folder = dirname($a->path);
                $b_folder = dirname($b->path);
                if ($a_folder != $b_folder) {
                    return strcmp($a_folder, $b_folder);
                } else {
                    //Archivos juntos del mismo tipo se comprimen mejor (compresión sólida)
                    $a_ext = pathinfo($a->path, PATHINFO_EXTENSION);
                    $b_ext = pathinfo($b->path, PATHINFO_EXTENSION);
                    if ($a_ext != $b_ext) {
                        return strcmp($a_ext, $b_ext);
                    } else {
                        return strcmp($a->path, $b->path);
                    }
                }
            }

        endif;

        usort($files, '_archive_sort_files');

        return $files;
    }

    /**
     * Crea el archivo comprimido en la ruta indicada
     * @return int Número de archivos añadidos
     */
    public abstract function create($path);
}

/**
 * @access private
 */
class Archive_File
{

    public $path;
    public $real_path = false;
    public $content = false;
    public $is_directory;

    public function stats()
    {
        if ($this->real_path) {
            return stat($this->real_path);
        } else {
            return array(
                'mode' => 0777, //Modo
                'uid' => 0, //Usuario
                'gid' => 0, //Grupo
                'size' => strlen($this->content), //Tamaño
                'mtime' => time(), //Fecha de modificación
            );
        }
    }

}
