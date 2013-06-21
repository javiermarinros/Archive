<?php

/**
 * Utilidad para la creación de archivos comprimidos ZIP.
 * 
 * Basada en la clase CI_Zip de Codeigniter y  TAR/GZIP/BZIP2/ZIP ARCHIVE CLASSES 2.1 de Devin Doucette 
 */
class Archive_Zip extends Archive_Base {

    /**
     * Comentario incluido en el archivo
     * @var string
     */
    public $comment;

    /**
     * Si se activa, los ficheros se almacenarán sin comprimir
     * @var boolean 
     */
    public $store_only = FALSE;

    public function create($dest_path) {
        $archiveh = fopen($dest_path, 'w+');

        if ($archiveh === FALSE)
            throw new Archive_Exception("Cannot create zip file on '$path'");


        $files = $this->_get_files();
        $file_count = 0;
        $current_offset = 0;
        $central = '';
        $gz_level = round(9 / 100 * $this->level);
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->path);
            $stats = $file->stats();

            if (!$stats || ($file->real_path && !($fileh = fopen($file->real_path, 'rb')))) {
                $this->log[] = "File {$file->real_path} could not be read";
                continue;
            }

            //Añadir bloque local
            $timedate = explode(" ", date("Y n j G i s", $stats['mtime']));
            $timedate = ($timedate[0] - 1980 << 25) | ($timedate[1] << 21) | ($timedate[2] << 16) |
                    ($timedate[3] << 11) | ($timedate[4] << 5) | ($timedate[5]);

            $block = pack("VvvvV", 0x04034b50, 0x000A, 0x0000, $this->store_only ? 0x0000 : 0x0008, $timedate);
            $crc32 = 0;
            $compressed_size = 0;
            $file_offset = $current_offset;
            if ($file->is_directory) {
                $path = rtrim($path, '/') . '/';
                $block .= pack("VVVvv", 0x00000000, 0x00000000, 0x00000000, strlen($path), 0x0000) . $path;

                fwrite($archiveh, $block);
            } else {
                if ($file->real_path) {
                    //Escribir bloque dummy (ya que no disponemos de toda la información aún)
                    $dummy_block = $block . pack("VVVvv", $crc32, $compressed_size, $stats['size'], strlen($path), 0x0000) . $path;
                    fwrite($archiveh, $dummy_block);

                    //Escribir archivo
                    $crc32 = $this->_write_file($fileh, $archiveh, $gz_level);

                    //Actualizar información del bloque local     
                    fseek($archiveh, 0, SEEK_END);
                    $compressed_size = ftell($archiveh) - $current_offset - strlen($dummy_block);

                    fseek($archiveh, $current_offset);
                    $block .= pack("VVVvv", $crc32, $compressed_size, $stats['size'], strlen($path), 0x0000);
                    $block .= $path;
                    fwrite($archiveh, $block);

                    fseek($archiveh, 0, SEEK_END);
                } else {
                    $crc32 = crc32($file->content);
                    $compressed = $this->store_only ? $file->content : gzdeflate($file->content, $gz_level);
                    $compressed_size = strlen($compressed);

                    $block .= pack("VVVvv", $crc32, $compressed_size, $stats['size'], strlen($path), 0x0000) . $path;

                    fwrite($archiveh, $block);
                    fwrite($archiveh, $compressed);
                    unset($compressed);
                }
            }
            $current_offset += strlen($block) + $compressed_size;
            $file_count++;


            //Añadir información central            
            $central .=
                    pack('V', 0x02014b50)//0	4	Central directory file header signature = 0x02014b50
                    . pack('v', 0x0014)// 4	2	Version made by
                    . pack('v', $this->store_only ? 0x0000 : 0x000A)// 6	2	Version needed to extract (minimum)
                    . pack('v', 0x0000)// 8	2	General purpose bit flag
                    . pack('v', $this->store_only ? 0x0000 : 0x0008)//10	2	Compression method
                    . pack('V', $timedate)//12	2	File last modification time 14	2	File last modification date
                    . pack('V', $crc32)//16	4	CRC-32
                    . pack('V', $compressed_size)// compressed filesize
                    . pack('V', $stats['size']) // uncompressed filesize
                    . pack('v', strlen($path)) // length of filename
                    . pack('v', 0) // extra field length
                    . pack('v', 0) // file comment length
                    . pack('v', 0) // disk number start
                    . pack('v', 0) // internal file attributes
                    . pack('V', $file->is_directory ? 16 : 32) // external file attributes - 'archive' bit set
                    . pack('V', $file_offset) // relative offset of local header
                    . $path;
        }

        //Escribir información central y finalizar
        fwrite($archiveh, $central);

        fwrite($archiveh, pack("VvvvvVVv", 0x06054b50, 0x0000, 0x0000, $file_count, // total # of entries "on this disk"
                        $file_count, // total # of entries "on this disk"
                        strlen($central), // size of central dir
                        $current_offset, // offset to start of central dir
                        strlen($this->comment)// .zip file comment length
        ));

        if (!empty($this->comment))
            fwrite($archiveh, $this->comment);

        fclose($archiveh);

        return $file_count;
    }

    /**
     * @param $fileh
     * @param $archiveh
     * @param $gz_level
     * @return number
     */
    private function _write_file($fileh, $archiveh, $gz_level) {
        if (!$this->store_only)
            $filter = stream_filter_append($archiveh, 'zlib.deflate', STREAM_FILTER_WRITE, array('level' => $gz_level));

        $crc = hash_init("crc32b");
        while ($temp = fread($fileh, 1048576)) {
            fwrite($archiveh, $temp);
            hash_update($crc, $temp);
        }

        $crc32 = hexdec(hash_final($crc));
        if (!$this->store_only)
            stream_filter_remove($filter);
        fclose($fileh);
        return $crc32;
    }

}