<?php

/**
 * Utilidad para la creación de archivos TAR, TGZ y TAR.BZ2
 *
 * Basada en  TAR/GZIP/BZIP2/ZIP ARCHIVE CLASSES 2.1 de Devin Doucette
 */
class Archive_Tar extends Archive_Base
{

    const NO_COMPRESS = 1;
    const COMPRESS_GZIP = 2;
    const COMPRESS_BZIP2 = 2;

    public function __construct($mode = self::COMPRESS_GZIP)
    {
        $this->mode = $mode;
        parent::__construct();
    }

    /**
     * Indica el modo en el que se creará el fichero (tar, tgz, tarbz, etc.)
     * @var int
     */
    public $mode = self::COMPRESS_GZIP;

    private function _write($fileh, $data)
    {
        switch ($this->mode) {
            case self::COMPRESS_GZIP:
                gzwrite($fileh, $data);
                break;

            case self::COMPRESS_BZIP2:
                bzwrite($fileh, $data);
                break;

            default:
                fwrite($fileh, $data);
                break;
        }
    }

    public function create($path)
    {
        $files = $this->_get_files();

        //Abrir fichero
        switch ($this->mode) {
            case self::COMPRESS_GZIP:
                $archiveh = gzopen($path, 'wb' . round(9 / 100 * $this->level));
                break;

            case self::COMPRESS_BZIP2:
                $archiveh = bzopen($path, 'wb' . round(9 / 100 * $this->level));
                break;

            default:
                $archiveh = fopen($path, 'w');
                break;
        }
        if (!$archiveh) {
            throw new RuntimeException("File '$path' cannot be opened");
        }

        //Escribir fichero TAR
        $file_count = 0;
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->path);
            if (strlen($path) > 99) {
                //Dividir la ruta en dos debido a las limitaciones de TAR
                $prefix = substr($path, 0, strrpos(substr($path, 0, min(154, strlen($path))), '/') + 1);
                $path = substr($path, strlen($prefix));

                if (strlen($prefix) > 154 || strlen($path) > 99) {
                    $this->log[] = "Could not add '$file->path' to archive because the filename is too long.";
                    continue;
                }
            } else {
                $prefix = '';
            }

            //Crear cabecera del archivo
            $stats = $file->stats();
            $link = $file->real_path && is_link($file->real_path);

            if (!$stats || ($file->real_path && !($fileh = fopen($file->real_path, 'rb')))) {
                $this->log[] = "File {$file->real_path} could not be read";
                continue;
            }

            $block = pack(
                "a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12", //Formato
                $file->path, //0	100	File name
                sprintf("%07o", $stats['mode']), //100	8	File mode
                sprintf("%07o", $stats['uid']), //108	8	Owner's numeric user ID
                sprintf("%07o", $stats['gid']), //116	8	Group's numeric user ID
                sprintf("%011o", $stats['size']), //124	12	File size in bytes (octal basis)
                sprintf("%011o", $stats['mtime']), //136	12	Last modification time in numeric Unix time format (octal)
                "        ", //148	8	Checksum for header record
                $link ? 2 : ($file->is_directory ? 5 : 0), //156	1	Type flag
                $link ? readlink($file->real_path) : "", //157	100	Name of linked file
                "ustar ", //257	6	UStar indicator "ustar"
                " ", //263	2	UStar version "00"
                "Unknown", //265	32	Owner user name
                "Unknown", //297	32	Owner group name
                "", //329	8	Device major number
                "", //337	8	Device minor number
                $prefix, //345	155	Filename prefix
                ""
            );

            $checksum = 0;
            for ($i = 0; $i < 512; $i++) {
                $checksum += ord(substr($block, $i, 1));
            }
            $checksum = pack("a8", sprintf("%07o", $checksum));
            $block = substr_replace($block, $checksum, 148, 8);

            //Escibir cabecera y contenido
            $this->_write($archiveh, $block);
            if (!$link && $stats['size'] != 0) {
                if ($file->real_path) {
                    while ($temp = fread($fileh, 1048576)) {
                        $this->_write($archiveh, $temp);
                    }
                } else {
                    $this->_write($archiveh, $file->content);
                }

                //Escribir relleno (TAR funciona en bloques de 512 bytes)
                if ($stats['size'] % 512 > 0) {
                    $this->_write($archiveh, str_pad('', 512 - $stats['size'] % 512), "\0");
                }

                if ($file->real_path) {
                    fclose($fileh);
                }
            }

            $file_count++;
        }

        //Cerrar fichero
        switch ($this->mode) {
            case self::COMPRESS_GZIP:
                gzclose($archiveh);
                break;

            case self::COMPRESS_BZIP2:
                bzclose($archiveh);
                break;

            default:
                fclose($archiveh);
                break;
        }

        return $file_count;
    }

}
