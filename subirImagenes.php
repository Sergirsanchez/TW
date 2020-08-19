<?php
    include("db.php");

    header('Content-Type: application/json');

    $result = [];

    if(!empty($_FILES['images']['name'][0])){    /*Recibimos la variable de AJAX*/
        

        for($i = 0; $i < sizeof($_FILES['images']['name']); $i++){  /*Lo haremos de forma que podamos meter todas las imágenes o ninguna*/
            
            $file_size = $_FILES['images']['size'][$i];
            $file_name = $_FILES['images']['name'][$i];

            $extensions= array("jpeg","jpg","png");
            
            $file_ext = explode(".",$file_name);
            $file_ext = $file_ext[(count($file_ext))-1];

            if (in_array($file_ext,$extensions) === false){ /*Error de tipo 1: Extensión no permitida*/
              $result = 1;
              break;
            }
            
            if ($file_size > 2097152){  /*Error de tipo 2: Tamaño mayor a 2 MB*/
              $result  = 2;
              break;
            }

        }

        if($result != 1 & $result != 2){  /*No hemos tenido error*/

            for($i = 0; $i < sizeof($_FILES['images']['name']); $i++){
                $file_name = $_FILES['images']['name'][$i];
                $file_tmp = $_FILES['images']['tmp_name'][$i];

                if(!file_exists("tmp/".$file_name)){    /*No tenemos esa imagen ya metida en el fichero temporal*/
                    move_uploaded_file($file_tmp, "tmp/".$file_name); /*Lo movemos todo al fichero temporal*/
                }
            }

            /*Listamos todos los nombre de ficheros del directorio /tmp, y los metemos en la lista*/
            $files = glob("tmp/*");

            foreach($files as &$file){
                if($file != "." & $file != ".."){
                    array_push($result,$file);
                }
            }

        }

        
    }

    else{
        header("Location:portada.php");
    }
    

    echo(json_encode($result));

?>