<?php

    function connectDB(){

        $mysqli = new mysqli("localhost", "usuario", "usuario", "TW");
        $mysqli->set_charset("utf8");   //Para que acepte la "ñ" como caracter; si no se incluye, cualquier consulta que incluya la "ñ" dará resultado vacío
    
        if ($mysqli->connect_errno) {
            echo ("Fallo al conectar: " . $mysqli->connect_error);
        }

        return $mysqli;
    
    }

    function createUsers(){
        $mysqli = connectDB();

        /*Primero, encriptamos la contraseña de los usuarios*/
        $normalUserPassword=password_hash("usuario",PASSWORD_DEFAULT);
        $adminPassword=password_hash("admin",PASSWORD_DEFAULT);
        

        /*Introducimos los usuarios en la base de datos*/
            $insertCommand = $mysqli->prepare("INSERT INTO usuarios(nombre,apellidos,email,tipo,foto,contraseña) VALUES
                                            ('El Cocinillas','Cocinas','cocinillas@ugr.es','usuario','ElCocinillasCocinas.jpg',?)");
            
            $insertCommand->bind_param("s",$normalUserPassword);
            $insertCommand->execute();
            $insertCommand->close();

        
        $insertCommand = $mysqli->prepare("INSERT INTO usuarios(nombre,apellidos,email,tipo,foto,contraseña) VALUES
        ('admin','admin','admin@ugr.es','admin','adminadmin.jpg',?)");

        $insertCommand->bind_param("s",$adminPassword);
        $insertCommand->execute();
        $insertCommand->close();

        $insertCommand = $mysqli->query("INSERT INTO usuarios(nombre,tipo) VALUES ('Anónimo','no registrado')");
        

    }

    //createUsers();

    /*-------------------------------- FUNCIÓN PARA OBTENER LOS DATOS DE UNA RECETA ---------------------------------*/
    function getData($name){

        $mysqli = connectDB();

        $tagsID = []; /*Almacenamos las IDs de las etiquetas*/
        $tagsNames = []; /*Almacenamos el nombre de las etiquetas*/
        $comments = []; /*Toda la info relativa a cada comentario unida*/
        $commentsUsersID = []; /*Almacenamos las IDs de los usuarios de los comentarios para obtener sus nombres*/
        $commentsUsersNames = []; /*Almacenamos los nombres de los usuarios de los comentarios */
        $commentsUsersSurnames = []; /*Almacenamos los apellidos de los usuarios de los comentarios */
        $commentsText = []; /*Almacenamos el texto de los comentarios*/
        $commentsDates = []; /*Almacenamos las fechas de los comentarios*/
        $photos = []; /*Almacenamos el enlace de todas las imágenes*/
        $rates = []; /*Almacenamos las notas de la receta*/  



        /*----------------------- OBTENEMOS EL TEXTO RELATIVO A LA RECETA ----------------------------------*/
            /*------------------------ Usamos prepared statements para evitar inyecciones SQL----------------*/
            $selectCommand = $mysqli->prepare("SELECT * FROM recetas WHERE nombre=?");
            $selectCommand->bind_param("s",$name);
            $selectCommand->execute();

            $getInfo = $selectCommand->get_result();
            
            if($getInfo->num_rows > 0){
                $result = $getInfo->fetch_assoc();
                $recipe = ['id'=>$result['id'],'nombre'=>$result['nombre'],'idautor'=>$result['idautor'],'descripcion'=>$result['descripcion'],'ingredientes'=>$result['ingredientes'],
                             'preparacion'=>$result['preparacion']];
            }

            $selectCommand->close();


        /*---------------------- OBTENEMOS LA INFORMACIÓN RELATIVA A LAS CATEGORÍAS Y AL NOMBRE DE USUARIO------------*/
            /*------------------------ Usamos prepared statements para evitar inyecciones SQL----------------*/
            
            /*Obtenemos el nombre del creador*/
            $selectCommand = $mysqli->prepare("SELECT * FROM usuarios WHERE id=?");
            $selectCommand->bind_param("i",$recipe['idautor']);
            $selectCommand->execute();

            $getInfo = $selectCommand->get_result();
            
            if($getInfo->num_rows > 0){
                while($result = $getInfo->fetch_assoc()){
                   $recipe['nombreautor'] = $result['nombre'];
                   $recipe['apellidosautor'] = $result['apellidos']; 
                }
            }

            $selectCommand->close();

            /*Obtenemos las categorías*/
                /*Primero obtenemos los id en listacategorias*/

                $selectCommand = $mysqli->prepare("SELECT categorias_id FROM categorias WHERE receta_id=?");
                $selectCommand->bind_param("i",$recipe['id']);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();
                
                if($getInfo->num_rows > 0){
                    while($result = $getInfo->fetch_assoc()){
                        array_push($tagsID,$result['categorias_id']);
                    }
                }

                $selectCommand->close();

                /*Segundo, obtenemos los nombres asociados a esas IDs, y las almacenamos en la receta*/

                foreach($tagsID as &$tag){  /*Para cada id*/
                    $selectCommand = $mysqli->prepare("SELECT nombre FROM listacategorias WHERE id=?");
                    $selectCommand->bind_param("i",$tag);
                    $selectCommand->execute();

                    $getInfo = $selectCommand->get_result();
                    
                    if($getInfo->num_rows > 0){
                        while($result = $getInfo->fetch_assoc()){
                            array_push($tagsNames,$result['nombre']);
                        }
                    }

                    $selectCommand->close();
                }

                $recipe['etiquetas'] = $tagsNames;
                

        /*------------------------- OBTENEMOS LA INFO RELATIVA A LOS COMENTARIOS --------------------------*/
            /*------------------------ Usamos prepared statements para evitar inyecciones SQL----------------*/
        
            /*Primero, obtenemos los comentarios en sí, la fecha de publicación y el ID de los usuarios que los publicaron*/
                $selectCommand = $mysqli->prepare("SELECT * FROM comentarios WHERE idreceta=?");
                $selectCommand->bind_param("i",$recipe['id']);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();
                

                if($getInfo->num_rows > 0){
                    while($result = $getInfo->fetch_assoc()){
                        array_push($commentsUsersID,$result['idusuario']);
                        array_push($commentsText,$result['comentario']);
                        array_push($commentsDates,$result['fecha']);
                    }
                }

                $selectCommand->close();


            /*Segundo, obtenemos los nombres de usuarios asociados a esas IDs, y lo almacenamos en un array toda la info de cada comentario junta*/
                for($i = 0, $size = count($commentsUsersID); $i < $size; ++$i ){

                    $selectCommand = $mysqli->prepare("SELECT * FROM usuarios WHERE id=?");
                    $selectCommand->bind_param("i",$commentsUsersID[$i]);
                    $selectCommand->execute();

                    $getInfo = $selectCommand->get_result();
                    

                    if($getInfo->num_rows > 0){
                        $result = $getInfo->fetch_assoc();
                        $userName = $result['nombre'];
                        $userSurname = $result['apellidos'];

                        array_push($commentsUsersNames,$userName);
                        array_push($commentsUsersSurnames,$userSurname);
                    }
                    $selectCommand->close();
                }

                $comments['nombreAutor'] = $commentsUsersNames;
                $comments['apellidosAutor'] = $commentsUsersSurnames;
                $comments['texto'] = $commentsText;
                $comments['fecha'] = $commentsDates;

                $recipe['comentarios'] = $comments;

        /*------------------------- OBTENEMOS LA INFO RELATIVA A LAS IMAGENES --------------------------*/
            /*------------------------ Usamos prepared statements para evitar inyecciones SQL----------------*/
                $selectCommand = $mysqli->prepare("SELECT fichero FROM fotos WHERE idreceta=?");
                $selectCommand->bind_param("i",$recipe['id']);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();
                
                if($getInfo->num_rows > 0){
                    while($result = $getInfo->fetch_assoc()){
                        array_push($photos,$result['fichero']);                        
                    }
                    
                    $recipe['fotos'] = $photos;
                }

                $selectCommand->close();

        /* Por último, obtenemos la valoración media de la receta*/
            $selectCommand = $mysqli->prepare("SELECT valoracion FROM valoraciones WHERE idreceta=?");
            $selectCommand->bind_param("i",$recipe['id']);
            $selectCommand->execute();

            $getInfo = $selectCommand->get_result();
            
            if($getInfo->num_rows > 0){
                while($result = $getInfo->fetch_assoc()){
                    array_push($rates,$result['valoracion']);                        
                }
            
            }

            $selectCommand->close();

            /*Guardamos el número total de votos, para mostarlos*/
            $recipe['totalRates'] = count($rates);

            /*Calculamos la media de todos los botos*/
            $resultRates = 0;

            if(count($rates) > 0){

                foreach($rates as &$singleRate){
                    $resultRates += $singleRate;
                }

                $resultRates = $resultRates / count($rates);
                $resultRates = round($resultRates,1); /*Redondeamos con un decimal*/
            }

            $recipe['resultRates'] = $resultRates;


        return $recipe;
    }

    /*------------------------------- FUNCIÓN PARA CALCULAR EL NÚMERO DE ELEMENTOS DE LA TABLA DE RECETAS ------------------------*/
    function getTotalRecipes(){
        $mysqli = connectDB();
        $selectCommand = $mysqli->query("SELECT COUNT(*) FROM recetas");

        if($selectCommand->num_rows > 0){
            $result = $selectCommand->fetch_row();
            $totalRecipes = $result[0];
        }

        return $totalRecipes;
    }


    /*---------------------------------- FUNCIÓN PARA OBTENER LOS COMENTARIOS DEL FAQ ---------------------------------------------*/
    function getFAQComments(){
        $FAQUsersID = []; /*Almacenamos las IDs de los usuarios que han comentado*/
        $FAQUsersNames = []; /*Almacenamos los nombres de los usuarios que han comentado*/
        $FAQComments = []; /*Los comentarios como tal*/
        $FAQDates = []; /*Las fechas de los comentarios*/
        $comments = []; /*todos los comentarios, con sus respectivos datos*/

        $mysqli = connectDB();
        

        /*Primero, obtenemos lso comentarios en sí, las fechas y los IDs de los autores*/
            $selectCommand = $mysqli->query("SELECT * FROM comentarios WHERE idreceta is NULL");

            if($selectCommand->num_rows > 0){
                while($result = $selectCommand->fetch_assoc()){
                    array_push($FAQUsersID,$result['idusuario']);
                    array_push($FAQComments,$result['comentario']);
                    array_push($FAQDates,$result['fecha']);
                }
            }


        /*Segundo, obtenemos los nombres de los usuarios asociados a esas IDs*/
            for($i = 0, $size = count($FAQUsersID); $i < $size; ++$i){  /*Para cada ID*/
                $selectCommand = $mysqli->prepare("SELECT nombre FROM usuarios WHERE id=?");

                $selectCommand->bind_param("i",$FAQUsersID[$i]);
                $selectCommand->execute();
    
                $getInfo = $selectCommand->get_result();
    
                if($getInfo->num_rows > 0){
                    $result = $getInfo->fetch_assoc();
                    $userName = $result['nombre']; 
                }

                $selectCommand->close();


                array_push($FAQUsersNames,$userName);
            }

            $comments['autor'] = $FAQUsersNames;
            $comments['fecha'] = $FAQDates;
            $comments['texto'] = $FAQComments;
           
        return $comments;
    }

    /*------------------------------- FUNCIÓN PARA OBTENER LOS NOMBRES DE LAS RECETAS BUSCADAS ---------------------------------*/

    function getNames($originalName){
        $mysqli = connectDB();
        $nameToSearch = $originalName;   /*String donde combinamos la expresión regular a usar con el nombre da la receta dado*/
        $names = [];   /*Array donde vamos a almacenar los nombres*/

        /*No nos olvidemos de los prepared statements*/
        if($originalName != "mostrar todos"){
            $selectCommand = $mysqli->prepare("SELECT nombre FROM recetas WHERE nombre REGEXP ? ORDER BY nombre ASC");
            $selectCommand->bind_param("s",$nameToSearch);
        }

        else{/*Si escribimos en la barra "mostrar todos" o no ponemos nada, mostramos todas las recetas*/
            $selectCommand = $mysqli->prepare("SELECT nombre FROM recetas ORDER BY nombre ASC");

        }


        $selectCommand->execute();

        $getInfo = $selectCommand->get_result();

        if($getInfo->num_rows > 0){
            
            while($result = $getInfo->fetch_assoc()){
                array_push($names,$result['nombre']); 

            }
        }

        $selectCommand->close();

        return $names;
    }

    /*------------------------------ FUNCIÓN PARA AÑADIR UNA RECETA NUEVA -------------------------------------------------------*/

    function addRecipe($title,$author,$authorSurname,$describe,$steps,$categories,$ingredients){
        $mysqli = connectDB();
        $categoriesID = []; /*Almacenamos las IDs de las categorías*/


       /*-------------------- Metemos la información relativa a la tabla de recetas----------------------*/
            /*Primero, vamos a obtener la ID del usuario creador de la receta*/
                    $selectCommand = $mysqli->prepare("SELECT id FROM usuarios WHERE nombre=?");

                    $selectCommand->bind_param("s",$author);
                    $selectCommand->execute();

                    $getInfo = $selectCommand->get_result();

                    if($getInfo->num_rows > 0){
                        $result = $getInfo->fetch_assoc();
                        $userID = $result['id']; 
                    }

                    $selectCommand->close();

            /*Segundo, metemos toda la info*/
                    $insertCommand = $mysqli->prepare("INSERT INTO recetas(idautor, nombre, descripcion, ingredientes, preparacion) VALUES(?,?,?,?,?)");

                    $insertCommand->bind_param("issss",$userID,$title,$describe,$ingredients,$steps);
                    $insertCommand->execute();
                    $insertCommand->close();

        /*-------------------- Metemos la información relativa a la tabla de categorias----------------------*/

            /*Primero, lo introducimos en listacategorias, y obtenemos su ID en esa tabla*/
                foreach($categories as &$category){ /*Para cada categoría*/
                    $insertCommand = $mysqli->prepare("INSERT INTO listacategorias(nombre) VALUES(?)"); /*lo introducimos*/

                    $insertCommand->bind_param("s",$category);
                    $insertCommand->execute();
                    $insertCommand->close();


                    $selectCommand = $mysqli->prepare("SELECT id FROM listacategorias WHERE nombre=?"); /*Obtenemos su ID*/

                    $selectCommand->bind_param("s",$category);
                    $selectCommand->execute();

                    $getInfo = $selectCommand->get_result();

                    if($getInfo->num_rows > 0){
                        $result = $getInfo->fetch_assoc();
                        array_push($categoriesID,$result['id']); 
                        
                    }

                    $selectCommand->close();

                }

            /*Segundo, introducimos todas esas categorías en la lista "categorías", pero antes debemos obtener la id de la receta*/
                $selectCommand = $mysqli->prepare("SELECT id FROM recetas WHERE nombre=?"); /*Obtenemos la ID*/

                $selectCommand->bind_param("s",$title);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();

                if($getInfo->num_rows > 0){
                    $result = $getInfo->fetch_assoc();
                    $recipeID = $result['id']; 
                    
                }

                $selectCommand->close();
                

                for($i = 0, $size = count($categoriesID); $i < $size; ++$i){    /*introducimos las recetas*/
                    $insertCommand = $mysqli->prepare("INSERT INTO categorias(receta_id,categorias_id) VALUES(?,?)"); /*lo introducimos*/

                    $insertCommand->bind_param("ii",$recipeID,$categoriesID[$i]);
                    $insertCommand->execute();
                    $insertCommand->close();
                }
            

        /*Añadimos la entrada a la tabla de logging*/
        $message = $author." ".$authorSurname." ha añadido la receta '".$title."'";
        insertIntoLog($message);
        

    }

    /*------------------------------------- FUNCIÓN PARA VALIDAR UNA RECETA ---------------------------------*/
    function validRecipe($name){
        $mysqli = connectDB();
        $isValid = 0;

        $selectCommand = $mysqli->prepare("SELECT COUNT(*) FROM recetas WHERE nombre=?"); /*Obtenemos la ID*/

        $selectCommand->bind_param("s",$name);
        $selectCommand->execute();

        $getInfo = $selectCommand->get_result();

        if($getInfo->num_rows > 0){
            $result = $getInfo->fetch_assoc();
            $numberOfRecipes = $result["COUNT(*)"]; 
                    
        }

        $selectCommand->close();
    
        if($numberOfRecipes > 0){
            $isValid = 1;
        }

        return $isValid;
    }
     
    /*-------------------------------------- FUNCIÓN PARA ELIMINAR UNA RECETA -------------------------------*/
    function removeRecipe($userName,$userSurname,$name){
        $mysqli = connectDB();
        $tagsID = []; /*Almacenamos las IDs de las categorias a eliminar*/
        $photosArray = []; /*Almacenamos los nombres de las imagenes a borrar*/

        /*Primero, obtenemos el ID de la receta*/
            $selectCommand = $mysqli->prepare("SELECT id FROM recetas WHERE nombre=?"); /*Obtenemos la ID*/

            $selectCommand->bind_param("s",$name);
            $selectCommand->execute();

            $getInfo = $selectCommand->get_result();

            if($getInfo->num_rows > 0){
                $result = $getInfo->fetch_assoc();
                $recipeID = $result['id']; 
            }

            $selectCommand->close();

        /*Eliminamos las imágenes*/

            /*Primero, obtenemos el nombre de las imágenes, para poder pasarlas al directorio de imágenes borradas*/
            $selectCommand = $mysqli->prepare("SELECT fichero FROM fotos WHERE idreceta=?");
            $selectCommand->bind_param("i",$recipeID);
            $selectCommand->execute();

            $getResult = $selectCommand->get_result();

            if($getResult->num_rows > 0){ /*Si hay mas de 0 filas es porque el nombre ya está*/
                while($photo = $getResult->fetch_assoc()){
                array_push($photosArray,$photo['fichero']);
                }        
            }
        
            $selectCommand->close();

            /*Segundo, las pasamos al directorio de imágenes borradas*/
                foreach($photosArray as &$photo){
                    if($photo != "no-image.jpg"){
                    rename("Images/".$photo, "deletedImages/".$photo); /*Movemos la imagen al directorio de borrados*/
                    }
                }

            /*por último, las borramos de la BD*/
            $deleteCommand = $mysqli->prepare("DELETE FROM fotos WHERE idreceta=?");   /*Eliminamos de la tabla "fotos"*/
            $deleteCommand->bind_param("i",$recipeID);
            $deleteCommand->execute();
            $deleteCommand->close();
        
        /*Eliminamos los comentarios*/
        $deleteCommand = $mysqli->prepare("DELETE FROM comentarios WHERE idreceta=?");   /*Eliminamos de la tabla "comentarios"*/
        $deleteCommand->bind_param("i",$recipeID);
        $deleteCommand->execute();
        $deleteCommand->close();

        /*Eliminamos las categorías*/
            /*Primero, tenemos que obtener las IDs de las categorías a eliminar*/
                $selectCommand = $mysqli->prepare("SELECT categorias_id FROM categorias WHERE receta_id=?"); /*Obtenemos la ID*/

                $selectCommand->bind_param("i",$recipeID);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();

                if($getInfo->num_rows > 0){

                    while($result = $getInfo->fetch_assoc()){
                        array_push($tagsID,$result['categorias_id']);
                    }
                }

                $selectCommand->close();

            /*Eliminamos de la tabla categorias*/
                $deleteCommand = $mysqli->prepare("DELETE FROM categorias WHERE receta_id=?");   /*Eliminamos de la tabla "categorias"*/
                $deleteCommand->bind_param("i",$recipeID);
                $deleteCommand->execute();
                $deleteCommand->close();

            /*Eliminamos de la tabla listacategorias*/

                foreach($tagsID as &$ID){
                    $deleteCommand = $mysqli->prepare("DELETE FROM listacategorias WHERE id=?");   /*Eliminamos de la tabla "listacategorias"*/
                    $deleteCommand->bind_param("i",$ID);
                    $deleteCommand->execute();
                    $deleteCommand->close();
                }
        
        /*Por último, eliminamos el registro de la tabla de recetas*/
            $deleteCommand = $mysqli->prepare("DELETE FROM recetas WHERE nombre=? and id=?");   /*Eliminamos de la tabla "recetas"*/
            $deleteCommand->bind_param("si",$name,$recipeID);
            $deleteCommand->execute();
            $deleteCommand->close();

        /*Añadimos la entrada a la tabla de logging*/
        $message = "Se ha eliminado la receta '".$name."'";
        insertIntoLog($message);
    }

    /*---------------------------------------------- FUNCIÓN PARA CREAR COMENTARIOS ----------------------------------------*/
    function createComment($authorName,$authorSurname,$recipeName,$commentText){
        $mysqli = connectDB();

        /*Primero, obtenemos la ID y el apellido asociada a ese usuario*/
            if($authorSurname != null){
                $selectCommand = $mysqli->prepare("SELECT * FROM usuarios WHERE nombre=? and apellidos=?"); /*Obtenemos la ID*/
                $selectCommand->bind_param("ss",$authorName,$authorSurname);
            }

            else{   /*En el caso de crearlo como "Anónimo, que no tenemos apellidos*/
                $selectCommand = $mysqli->prepare("SELECT * FROM usuarios WHERE nombre=?"); /*Obtenemos la ID*/
                $selectCommand->bind_param("s",$authorName);
            }



            $selectCommand->execute();

            $getInfo = $selectCommand->get_result();

            if($getInfo->num_rows > 0){
                $result = $getInfo->fetch_assoc();
                $userID = $result['id'];
                $authorSurname = $result['apellidos'];
            }

            $selectCommand->close();

        /*Introducimos los datos dentro de la tabla de comentatios*/
            if($recipeName == null){    /*Comentario de FAQ*/
                $insertCommand = $mysqli->prepare("INSERT INTO comentarios(idusuario,comentario,fecha) VALUES(?,?,now())"); /*lo introducimos*/

                $insertCommand->bind_param("is",$userID,$commentText);
                $insertCommand->execute();
                $insertCommand->close();
            }

            else{   /*Comentario dentro de una receta*/

                /*Primero, obtenemos el ID de la receta*/
                $selectCommand = $mysqli->prepare("SELECT id FROM recetas WHERE nombre=?"); /*Obtenemos la ID*/

                $selectCommand->bind_param("s",$recipeName);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();

                if($getInfo->num_rows > 0){
                    $result = $getInfo->fetch_assoc();
                    $recipeID = $result['id'];
                }

                $selectCommand->close();

                /*Segundo, introducimos el comentario*/
                $insertCommand = $mysqli->prepare("INSERT INTO comentarios(idusuario,idreceta,comentario,fecha) VALUES(?,?,?,now())"); /*lo introducimos*/

                $insertCommand->bind_param("iis",$userID,$recipeID,$commentText);
                $insertCommand->execute();
                $insertCommand->close();
            }

        /*Añadimos la entrada a la tabla de logging*/
        $message = "El usuario ".$authorName." ".$authorSurname." ha creado un comentario en la receta ".$recipeName.": '".$commentText."'";
        insertIntoLog($message);

    }

    /*--------------------------------------------------- FUNCIÓN PARA MODIFICAR UNA RECETA --------------------------------------*/
    function modifyRecipe($userName,$userSurname,$recipeID, $recipeName,$recipeDescription,$recipeIngredients,$recipeSteps,$recipeCategories){
        $mysqli = connectDB();

        $tagsID = []; /*Almacenamos las IDs de las etiquetas*/


        /*una vez tenemos la ID, ya podemos modificar la tabla "recetas"(EL NOMBRE A PARTE)*/

        $updateCommand = $mysqli->prepare("UPDATE recetas set nombre=?, descripcion=?,ingredientes=?,preparacion=? WHERE id=?");

        $updateCommand->bind_param("ssssi",$recipeName,$recipeDescription,$recipeIngredients,$recipeSteps,$recipeID);
        $updateCommand->execute();
        $updateCommand->close();

        
        
        /*Ahora vamos a reemplazar las categorias*/
            /*Primero, obtnemos las IDs de las categorias de la receta para poder eliminarlas*/
                $selectCommand = $mysqli->prepare("SELECT categorias_id FROM categorias WHERE receta_id=?"); /*Obtenemos la ID*/

                $selectCommand->bind_param("i",$recipeID);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();

                if($getInfo->num_rows > 0){
                   while($result = $getInfo->fetch_assoc()){
                        array_push($tagsID,$result['categorias_id']);
                   }
                 }

                $selectCommand->close();


   
            /*A continuación, borramos las entradas de ambas tablas*/
            $deleteCommand = $mysqli->prepare("DELETE FROM categorias WHERE receta_id=?");   /*Eliminamos de la tabla "categorias"*/
            $deleteCommand->bind_param("i",$recipeID);
            $deleteCommand->execute();
            $deleteCommand->close();

            foreach($tagsID as &$ID){
                $deleteCommand = $mysqli->prepare("DELETE FROM listacategorias WHERE id=?");   /*Eliminamos de la tabla "listacategorias"*/
                $deleteCommand->bind_param("i",$ID);
                $deleteCommand->execute();
                $deleteCommand->close();
            }

            /*Por último, añadimos las nuevas etiquetas*/
            foreach($recipeCategories as &$category){
                $insertCommand = $mysqli->prepare("INSERT INTO listacategorias (nombre) VALUES(?)"); /*lo introducimos en listacategorias*/

                $insertCommand->bind_param("s",$category);
                $insertCommand->execute();
                $insertCommand->close();


                $selectCommand = $mysqli->prepare("SELECT id FROM listacategorias WHERE nombre=?"); /*Obtenemos la ID en dicha tabla*/

                $selectCommand->bind_param("s",$category);
                $selectCommand->execute();

                $getInfo = $selectCommand->get_result();

                if($getInfo->num_rows > 0){
                   $result = $getInfo->fetch_assoc();
                   $currentCategoryID = $result['id'];

                 }

                $selectCommand->close();


                $insertCommand = $mysqli->prepare("INSERT INTO categorias (receta_id,categorias_id) VALUES(?,?)"); /*lo introducimos en categorias*/

                $insertCommand->bind_param("ii",$recipeID,$currentCategoryID);
                $insertCommand->execute();
                $insertCommand->close();

                } 
                
        /*Añadimos la entrada a la tabla de logging*/
        $message = "Se ha modificado la receta '".$recipeName."'";
        insertIntoLog($message);
    }

    /*----------------------------------------------------- FUNCIÓN PARA AÑADIR IMAGENES -------------------------------------------*/
    function addImages($images,$recipeName){

        $mysqli = connectDB();
        $totalPhotos = []; /*Array donde almacenamos las imágenes para buscar a "no-image.jpg"*/

         /*Primero, obtenemos el ID de la receta*/
         $selectCommand = $mysqli->prepare("SELECT id FROM recetas WHERE nombre=?"); /*Obtenemos la ID*/

         $selectCommand->bind_param("s",$recipeName);
         $selectCommand->execute();

         $getInfo = $selectCommand->get_result();

         if($getInfo->num_rows > 0){
             $result = $getInfo->fetch_assoc();
             $recipeID = $result['id']; 
         }

         $selectCommand->close();


    
        if(!empty($images)){  /*Si tenemos imagenes*/
    
           /*Comprobamos si tenemos la imagen "no-image.jpg" para eliminarla (significa que en la modificación hemos añadido las primeras imágenes a la receta)*/
           $select = $mysqli->prepare("SELECT * FROM fotos WHERE idreceta=?");
           $select->bind_param("i",$recipeID);
     
           $select->execute();
           $getResult = $select->get_result();
     
           if($getResult->num_rows > 0){
             while($photo = $getResult->fetch_assoc()){
               array_push($totalPhotos,$photo['fichero']);
             }

           }
           $select->close();
     
           $isInside = array_search('no-image.jpg',$totalPhotos);
    
     
           if($isInside == 0){ /*La imagen se encuentra dentro de la BD para ese evento (ocupa la primera posición), así que la borramos*/
             $photoName = "no-image.jpg";
     
             $deleteCommand = $mysqli->prepare("DELETE FROM fotos WHERE fichero=? and idreceta=?");   
             $deleteCommand->bind_param("si",$photoName,$recipeID);
             $deleteCommand->execute();
             $deleteCommand->close();
           }
           
    
          foreach($images as &$image){  /*Añadimos las imágenes una a una */
            $insertCommand = $mysqli->prepare("INSERT INTO fotos(idreceta,fichero) VALUES(?,?)");
            $insertCommand->bind_param("is",$recipeID,$image);
            $insertCommand->execute();
            $insertCommand->close();
          }
    
        }
    
        else{ /*No se ha incluido imagen*/
          /*Primero, veremos si ya teniamos imagenes previas; si no, introducimos la imagen de "no-image"*/
    
          $select = $mysqli->prepare("SELECT * FROM fotos WHERE idreceta=?");
          $select->bind_param("i",$recipeID);
    
          $select->execute();
          $getResult = $select->get_result();
    
          if($getResult->num_rows > 0){
            while($photo = $getResult->fetch_assoc()){
              array_push($totalPhotos,$photo['fichero']);
            }
          }
    
          $select->close();
    
          if(empty($totalPhotos)){  /*No tenemos imagenes previas*/
            $photoName='no-image.jpg';
    
            $insertCommand = $mysqli->prepare("INSERT INTO fotos(idreceta,fichero) VALUES(?,?)");
            $insertCommand->bind_param("is",$recipeID,$photoName);
            $insertCommand->execute();
            $insertCommand->close();
          }
    
        }
   
    }
    
    /*------------------------------------------------------------ FUNCIÓN PARA ELIMINAR UNA IMAGEN DE LA RECETA ---------------------------------------*/
    function removeImage($imageIndex,$arrayOfPhotos,$recipeID){
        $mysqli = connectDB();

        /*Primero, obtenemos el nombre de la imagen a borrar*/
        $photoName = $arrayOfPhotos[$imageIndex];

        /*Segundo, la borramos de la tabla de fotos*/
            $deleteCommand = $mysqli->prepare("DELETE FROM fotos WHERE fichero=? and idreceta=?");   
             $deleteCommand->bind_param("si",$photoName,$recipeID);
             $deleteCommand->execute();
             $deleteCommand->close();

        /*Tercero, eliminamos del array de imagenes*/
        unset($arrayOfPhotos[$imageIndex]); /*Borramos su valor*/
        $result = array_values($arrayOfPhotos); /*Reordenamos*/

        return $result;

    }

    /*------------------------------------------------------------- FUNCIÓN PARA OBTENER LAS RECETAS DEL USUARIO -----------------------------------*/
    function getMyRecipes($userID){
        $mysqli = connectDB();
        $totalRecipes = []; /*Almacenamos el nombre de todas las recetas de dicho usuario*/


        /*Obtenemos todas las recetas asociadas a ese usuario*/
        $select = $mysqli->prepare("SELECT nombre FROM recetas WHERE idautor=? ORDER BY nombre ASC");
        $select->bind_param("i",$userID);
    
        $select->execute();
        $getResult = $select->get_result();
    
        if($getResult->num_rows > 0){
            while($result = $getResult->fetch_assoc()){
                array_push($totalRecipes,$result['nombre']);
            }
        }
    
        $select->close();


        return $totalRecipes;

    }


    /*---------------------------------------------------------- FUNCIÓN PARA CHECKEAR EL LOGIN DE LOS USUARIOS --------------------------------*/
    function checkLogin($nick, $password) {
        $mysqli = connectDB();
        $usersNames = []; /*Almacenamos los nombres de los usuarios*/
        $usersPasswords = []; /*Almacenamos las contraseñas de los usuarios*/
        $logged = 0; /*Comprobamos si podemos o no loguearnos*/


        /*Obtenemos todos los usuarios de la lista*/
        $selectCommand = $mysqli->query("SELECT nombre,contraseña FROM usuarios WHERE nombre !='Anónimo' and tipo !='pendiente' ");
    
        if($selectCommand->num_rows > 0){
            while($result = $selectCommand->fetch_assoc()){
                array_push($usersNames,$result['nombre']);
                array_push($usersPasswords,$result['contraseña']);
            }
        }

        
        for ($i = 0 ; $i < sizeof($usersNames) ; $i++) {
          if ($usersNames[$i] === $nick) {
           
          if (password_verify($password, $usersPasswords[$i])) {
              $logged = 1;
          }

          }
        }

        return $logged;

      }

    /*------------------------------------------------------ FUNCIÓN PARA CHECKEAR SI UN USUARIO ESTÁ EN LA BD --------------------------------------*/
    function userExists($userName,$userSurname){
        $mysqli = connectDB();
        $exist = 0;
        $usersNames = []; /*Almacenamos la lista de los usuarios*/
        $usersSurnames = []; /*Almacenamos los apellidos si es necesario*/

        /*Obtenemos la lista de usuarios de la BD*/

        if($userSurname == null){
            $selectCommand = $mysqli->query("SELECT nombre FROM usuarios WHERE nombre !='Anónimo'");
        }

        else{
            $selectCommand = $mysqli->query("SELECT * FROM usuarios WHERE nombre !='Anónimo'");
        }

    
        if($selectCommand->num_rows > 0){
            while($result = $selectCommand->fetch_assoc()){
                array_push($usersNames,$result['nombre']);

                if($userSurname != null){
                    array_push($usersSurnames,$result['apellidos']);
                }
            }
        }

        if($userSurname == null){
            if(in_array($userName,$usersNames)){
                $exist = 1;
            }
        }

        else{
            if(in_array($userName,$usersNames) & in_array($userSurname,$usersSurnames)){
                $exist = 1;
            }
        }
        
        return $exist;

    }

    /*------------------------------------------------------ FUNCIÓN PARA OBTENER LOS DATOS DEL USUARIO -------------------------------*/
    function getUserData($userName, $userSurname=null){
        $mysqli = connectDB();

        if($userSurname != null){
            $select = $mysqli->prepare("SELECT * FROM usuarios WHERE nombre=? and apellidos=?");
            $select->bind_param("ss",$userName,$userSurname);
        }

        else{
            $select = $mysqli->prepare("SELECT * FROM usuarios WHERE nombre=? ");
            $select->bind_param("s",$userName);
        }
    
          $select->execute();
          $getResult = $select->get_result();
    
          if($getResult->num_rows > 0){
            $userData = $getResult->fetch_assoc();
            
          }
    
          $select->close();

        return $userData;
        
    }

    /*------------------------------------------------------ FUNCIÓN PARA OBTENER LOS DATOS DEL USUARIO POR SU ID -------------------------------*/
    function getUserDataByID($ID){
        $mysqli = connectDB();

            $select = $mysqli->prepare("SELECT * FROM usuarios WHERE id=?");
            $select->bind_param("i",$ID);

            $select->execute();
            $getResult = $select->get_result();
    
            if($getResult->num_rows > 0){
              $userData = $getResult->fetch_assoc();
            
            }
    
            $select->close();

        return $userData;
        
    }

    /*-------------------------------------------------- FUNCIÓN PARA MODIFICAR LOS DATOS DEL USUARIO---------------------------------------*/
    function modifyUserData($userOriginalName,$userOriginalSurname,$userName,$userSurname,$userEmail,$userPassword){

        $mysqli = connectDB();

        $userOriginalNameAux = str_replace(" ","",$userOriginalName);
        $userOriginalSurnameAux = str_replace(" ","",$userOriginalSurname);
        $userNameAux = str_replace(" ","",$userName);
        $userSurnameAux = str_replace(" ","",$userSurname);

        $photoOriginalName = $userOriginalNameAux.$userOriginalSurnameAux.".jpg";
        $photoNewName = $userNameAux.$userSurnameAux.".jpg";


        /*Primero, obtenemos la ID asociada al usuario*/
          $select = $mysqli->prepare("SELECT id FROM usuarios WHERE nombre=?");
          $select->bind_param("s",$userOriginalName);
    
          $select->execute();
          $getResult = $select->get_result();
    
          if($getResult->num_rows > 0){
             while($result = $getResult->fetch_assoc()){
                $userID = $result['id'];
             }
            
          }
    
          $select->close();

        /*Segundo, modificamos los datos*/


        if($userPassword != null){
            $userPassword = password_hash($userPassword,PASSWORD_DEFAULT);

            $updateCommand = $mysqli->prepare("UPDATE usuarios set nombre=?, apellidos=?,email=?,contraseña=?,foto=? WHERE id=?");
            $updateCommand->bind_param("sssssi",$userName,$userSurname,$userEmail,$userPassword,$photoNewName,$userID);
        }

        else{
            $updateCommand = $mysqli->prepare("UPDATE usuarios set nombre=?, apellidos=?,email=?,foto=? WHERE id=?");
            $updateCommand->bind_param("ssssi",$userName,$userSurname,$userEmail,$photoNewName,$userID);

        }
        
        $updateCommand->execute();
        $updateCommand->close();

        /*Cambiamos también el nombre de la foto, para que no se quede con el nombre original del usuario*/

        rename("usersProfiles/".$photoOriginalName,"usersProfiles/".$photoNewName);

        /*Añadimos los datos a la tabla de logging*/
        $message = $userName." ".$userSurname." acaba de modificar sus datos de usuario.";
        insertIntoLog($message);



    }

    /*------------------------------------------ FUNCIÓN PARA OBTENER LOS IDs DE LOS CREADORES DE UNA LISTA DE RECETAS --------------------------*/
    function getCreatorsID($recipes){
        $mysqli = connectDB();
        $authorsID = []; /*Almacenamos las IDs de los creadores*/

        foreach($recipes as &$singleRecipe){
        $select = $mysqli->prepare("SELECT idautor FROM recetas WHERE nombre=?");
          $select->bind_param("s",$singleRecipe);
    
          $select->execute();
          $getResult = $select->get_result();
    
          if($getResult->num_rows > 0){
            $result = $getResult->fetch_assoc();
            array_push($authorsID,$result['idautor']);
          }
    
          $select->close();
        }
        
        return $authorsID;
    }

    /*--------------------------------------- FUNCIÓN PARA LISTAR TODOS LOS USUARIOS ------------------------------------------------*/
    function listUsers($all = 1){
        $mysqli = connectDB();
        $usersList = []; /*Almacenamos toda la info*/

        if($all == 1){  /*Lista de usuarios ya registrados*/
            $select = $mysqli->query("SELECT * FROM usuarios WHERE nombre!='Anónimo' and tipo !='pendiente' ");
        }

        else{   /*Lista de usuarios pendientes de validar*/
            $select = $mysqli->query("SELECT * FROM usuarios WHERE tipo='pendiente' ");

        }

    
        if($select->num_rows > 0){
            while($result = $select->fetch_assoc()){
                array_push($usersList,$result);
            }
            
        }

        return $usersList;

        
    }

    /*----------------------------------- FUNCIÓN PARA ELIMINAR UN USUARIO -----------------------------------------*/
    function deleteUser($adminName,$adminSurname,$userName, $userSurname){
        $mysqli = connectDB();
        $recipesID = []; /*Almacenamos las IDs de las recetas*/


        /*Antes de nada, borramos la foto del directorio de usersProfiles*/
        $photoOriginalFirstPart = trim($userName," ");
        $photoOriginalSecondPart = trim($userSurname," ");
        $photoOriginalName = $photoOriginalFirstPart.$photoOriginalSecondPart.".jpg";

        rename("usersProfiles/".$photoOriginalName,"deletedImages/".$photoOriginalName);

        /*Obtenemos el ID del usuario*/
        $select = $mysqli->prepare("SELECT id FROM usuarios WHERE nombre=? and apellidos=?");
          $select->bind_param("ss",$userName,$userSurname);
    
          $select->execute();
          $getResult = $select->get_result();
    
          if($getResult->num_rows > 0){
             $result = $getResult->fetch_assoc();
             $userID = $result['id'];            
          }
    
          $select->close();

        /*Segundo, obtenemos las IDs de las recetas de ese usuario*/
        $select = $mysqli->prepare("SELECT id FROM recetas WHERE idautor=?");
          $select->bind_param("i",$userID);
    
          $select->execute();
          $getResult = $select->get_result();
    
          if($getResult->num_rows > 0){
             while($result = $getResult->fetch_assoc()){
                array_push($recipesID,$result['id']);
             }            
          }
    
          $select->close();

        /*Tercero, obtenemos la ID del usuario "Anónimo"*/
          $select = $mysqli->query("SELECT id FROM usuarios WHERE nombre='Anónimo'");
    
          if($select->num_rows > 0){
             $result = $select->fetch_assoc();
             $anonymousID = $result['id'];            
          }


        /*Cuarto, decimos que todas esas recetas ahora pasan a tener autor anónimo*/
        foreach($recipesID as &$singleID){
            $updateCommand = $mysqli->prepare("UPDATE recetas set idautor=? where id=?");

            $updateCommand->bind_param("ii",$anonymousID,$singleID);
            $updateCommand->execute();
            $updateCommand->close();
    
        }

        $deleteCommand = $mysqli->prepare("DELETE FROM usuarios WHERE nombre=? and apellidos=?");   /*Eliminamos el usuario*/
        $deleteCommand->bind_param("ss",$userName,$userSurname);
        $deleteCommand->execute();
        $deleteCommand->close();


        /*Añadimos la entrada a log*/
        $message = $adminName." ".$adminSurname." ha eliminado al usuario '".$userName." ".$userSurname."'";
        insertIntoLog($message);
    

    }

    /*-------------------------------------- FUNCIÓN PARA MODIFICAR LOS PRIVILEGIOS DE UN USUARIO ----------------------*/
    function modifyPrivileges($adminName,$adminSurname,$userName,$userSurname,$newPrivileges){
        $mysqli = connectDB();

        $updateCommand = $mysqli->prepare("UPDATE usuarios set tipo=? WHERE nombre=? and apellidos=?");

        $updateCommand->bind_param("sss",$newPrivileges,$userName,$userSurname);
        $updateCommand->execute();
        $updateCommand->close();

        /*Añadimos la entrada a la tabla de logging*/
        $message = "El admin ".$adminName." ".$adminSurname." ha cambiado los privilegios de ".$userName." ".$userSurname." a ".$newPrivileges;
        insertIntoLog($message);

    }

    /*----------------------------------- FUNCIÓN PARA CREAR UN USUARIO -------------------------------------------*/
    function createUser($adminName,$adminSurname,$name,$surname,$email,$password,$rol){
        $mysqli = connectDB();
        /*Primero, ciframos la contraseña*/
        $password = password_hash($password,PASSWORD_DEFAULT);

        /*Segundo, creamos el campo con el nombre de la foto*/
        $userNameAux = str_replace(" ","",$name);
        $userSurnameAux = str_replace(" ","",$surname);

        $photoOriginalName = $userNameAux.$userSurnameAux.".jpg";

        /*Tercero, introducimos los datos en la BD*/

        $insertCommand = $mysqli->prepare("INSERT INTO usuarios(nombre,apellidos,email,tipo,foto,contraseña) VALUES
                                            (?,?,?,?,?,?)");
            
        $insertCommand->bind_param("ssssss",$name,$surname,$email,$rol,$photoOriginalName,$password);
        $insertCommand->execute();
        $insertCommand->close();

        /*Último, ponemos una imagen por defecto para el usuario*/
        copy("usersProfiles/newUser.jpg","usersProfiles/".$photoOriginalName);

        /*Añadimos la operación a log*/
        $message = $adminName." ".$adminSurname." ha creado el usuario '".$name." ".$surname."'";
        insertIntoLog($message);
    }

    /*------------------------------- FUNCIÓN PARA BORRAR UN COMENTARIO ----------------------------------------------*/
    function deleteComment($adminName,$adminSurname,$name,$surname,$text,$recipe,$recipeID,$date){
        $mysqli = connectDB();

        /*Primero, obtenemos la ID del usuario*/
        $select = $mysqli->prepare("SELECT id FROM usuarios WHERE nombre=? and apellidos=?");
          $select->bind_param("ss",$name,$surname);
    
          $select->execute();
          $getResult = $select->get_result();
    
          if($getResult->num_rows > 0){
             $result = $getResult->fetch_assoc();
             $userID = $result['id'];            
          }
    
          $select->close(); 

          
          
        /*Eliminamos el comentario*/
            $deleteCommand = $mysqli->prepare("DELETE FROM comentarios WHERE idusuario=? and idreceta=? and comentario=? and fecha=?");   /*Eliminamos de la tabla "fotos"*/
            $deleteCommand->bind_param("iiss",$userID,$recipeID,$text,$date);
            $deleteCommand->execute();
            $deleteCommand->close();

        /*Enviamos el mensaje a logging*/
        $message = "El administrador ".$adminName." ".$adminSurname." ha eliminado en la receta ".$recipe." el comentario del usuario ".$name." ".$surname.": '".$text."'";
        insertIntoLog($message);
        
    }

    /*------------------------------------------------------------ FUNCIÓN PARA OBTENER LOS DATOS DE LOG ---------------------------*/
    function getLogData(){
        $mysqli = connectDB();
        $descripcion = []; /*Almacenamos las descripciones de los sucesos*/
        $fecha = []; /*Almacenamos la fecha de los sucesos*/

        $select = $mysqli->query("SELECT * FROM logging ORDER BY fecha DESC");
  
          if($select->num_rows > 0){
             while($result = $select->fetch_assoc()){
                array_push($descripcion,$result['descripcion']);
                array_push($fecha,$result['fecha']);
             }
          }

        $log = ['descripcion'=>$descripcion,'fecha'=>$fecha];

        return $log;
    }

    /*------------------------------------------------------ FUNCIÓN PARA INSERTA DATOS EN EL LOG ----------------------------------------------*/
    function insertIntoLog($message){
        $mysqli = connectDB();
            $insertCommand = $mysqli->prepare("INSERT INTO logging (fecha,descripcion) VALUES(now(),?)");
            $insertCommand->bind_param("s",$message);
            $insertCommand->execute();
            $insertCommand->close();
    }

    /*------------------------------------------------------- FUNCIÓN PARA VER LA COINCIDENCIA DE TEXTO DADA UNA LISTA DE RECETAS-----------------------------*/
    function lookForContentCoincidence($recipesReceived,$textToLookFor, $allFields = 1){
        $mysqli = connectDB();
        $recipesList = []; /*Almacenamos las recetas que contienen el texto*/
        $recipesID = []; /*Almacenamos las IDs de las recetas*/
        $categoriesID = []; /*Almacenamos las categorias de las recetas*/

        /*Primero, vemos la lista de las recetas que coincide con la búsqueda de entre todas*/

          if($allFields != null){   /*Buscamos en todos los campos*/
            $selectCommand = $mysqli->prepare("SELECT nombre FROM recetas WHERE nombre REGEXP ? or descripcion REGEXP ? or ingredientes REGEXP ? or preparacion REGEXP ? ORDER BY nombre ASC");

            $selectCommand->bind_param("ssss",$textToLookFor,$textToLookFor,$textToLookFor,$textToLookFor);

            $selectCommand->execute();
            $getResult = $selectCommand->get_result();
    
            if($getResult->num_rows > 0){
                while($result = $getResult->fetch_assoc()){
                    array_push($recipesList,$result['nombre']);
                }
            }
    
            $selectCommand->close(); 

          }

          else{ /*Buscamos solo en el campo de las categorias*/

            /*Primero, obtenemos las IDs de listacategorias*/
            $selectCommand = $mysqli->prepare("SELECT id FROM listacategorias WHERE nombre REGEXP ? ORDER BY nombre ASC");

            $selectCommand->bind_param("s",$textToLookFor);

            $selectCommand->execute();
            $getResult = $selectCommand->get_result();
    
            if($getResult->num_rows > 0){
                while($result = $getResult->fetch_assoc()){
                    array_push($categoriesID,$result['id']);
                }
            }
    
            $selectCommand->close();
            
            /*Obtenemos las IDs de las recetas con esas categorias*/
            foreach($categoriesID as &$singleCategory){
                $selectCommand = $mysqli->prepare("SELECT receta_id FROM categorias WHERE categorias_id=?");

                $selectCommand->bind_param("s",$singleCategory);

                $selectCommand->execute();
                $getResult = $selectCommand->get_result();
        
                if($getResult->num_rows > 0){
                    while($result = $getResult->fetch_assoc()){
                        array_push($recipesID,$result['receta_id']);
                    }
                }

                $selectCommand->close();
            }

            /*Por último, obtenemos el nombre de las recetas asociadas a esas IDs*/
            foreach($recipesID as &$singleRecipe){
                $selectCommand = $mysqli->prepare("SELECT nombre FROM recetas WHERE id=?");

                $selectCommand->bind_param("s",$singleRecipe);

                $selectCommand->execute();
                $getResult = $selectCommand->get_result();
        
                if($getResult->num_rows > 0){
                    while($result = $getResult->fetch_assoc()){
                        array_push($recipesList,$result['nombre']);
                    }
                }

                $selectCommand->close();
            }

            
          }

                   
        /*El resultado será la intersección de la lista que tenemos como argumento de entrada y la lista que hemos obtenido antes*/
            $result = array_intersect($recipesReceived,$recipesList);

        return $result;

    }

    /*----------------------------------------------- FUNCIÓN PARA OBTENER LAS RECETAS MÁS COMENTADAS -----------------------------------------------*/
    function getMostCommented($all = 0){
        $mysqli = connectDB();
        $recipesID = []; /*Almacenamos las IDs de las recetas*/
        $recipesNames = []; /*Almacenamos el nombre de las recetas*/

        /*Almacenamos las IDs de las recetas, de la más comentada a la menos*/
            $selectCommand = $mysqli->prepare("SELECT idreceta,COUNT(idreceta) FROM comentarios GROUP BY idreceta ORDER BY COUNT(idreceta) DESC");

            $selectCommand->execute();
            $getResult = $selectCommand->get_result();
        
            if($getResult->num_rows > 0){
                while($result = $getResult->fetch_assoc()){
                    array_push($recipesID,$result['idreceta']);
                }
            }

            $selectCommand->close();

        if($all == 0){  /*En el caso de obtener las 3 mas votadas*/
            /*Nos quedamos con las 3 primeras*/
            for ($i = 0; $i < 3; ++$i){
                $selectCommand = $mysqli->prepare("SELECT nombre FROM recetas WHERE id=?");

                $selectCommand->bind_param("i",$recipesID[$i]);

                $selectCommand->execute();
                $getResult = $selectCommand->get_result();
            
                if($getResult->num_rows > 0){
                    while($result = $getResult->fetch_assoc()){
                        array_push($recipesNames,$result['nombre']);
                    }
                }

                $selectCommand->close();

            }
        }

        else{   /*Caso de devolver la lista completa, para usarlo en ver que recetas fueron las más comentadas*/
            $nameOfRecipes = []; /*Alamcenamos los nombres en este caso, donde $recipeNames va a tener el nombre de las receetas y su valor*/
            foreach ($recipesID as &$singleRecipe){
                $selectCommand = $mysqli->prepare("SELECT nombre FROM recetas WHERE id=?");

                $selectCommand->bind_param("i",$singleRecipe);

                $selectCommand->execute();
                $getResult = $selectCommand->get_result();
            
                if($getResult->num_rows > 0){
                    while($result = $getResult->fetch_assoc()){
                        array_push($recipesNames,$result['nombre']);
                    }
                }

                $selectCommand->close();

            }

        }
        

        return $recipesNames;
    }

    /*------------------------------------------------ FUNCIÓN PARA HACER UN BACKUP DE LA DB -----------------------------------------------*/
    function backup(){
        $mysqli = connectDB();
        $tables = []; /*Almacenamos el nombre de las tablas*/

        /* Obtener listado de tablas*/

        $result= $mysqli->query("SHOW TABLES");
        while($row = mysqli_fetch_row($result)){
            $tables[] = $row[0];

        }

        /* Salvar cada tabla*/
        $salida = '';
        
        foreach($tables as &$singleTable) {
            $result= $mysqli->query('SELECT * FROM '.$singleTable);
            $num= mysqli_num_fields($result);
            $salida .= 'DROP TABLE '.$singleTable.';';
            
            $row2 = mysqli_fetch_row($mysqli->query('SHOW CREATE TABLE '.$singleTable));
            $salida .= "\n\n".$row2[1].";\n\n";  // row2[0]=nombre de tabla
            
            while($row = mysqli_fetch_row($result)) {
                $salida .= 'INSERT INTO '.$singleTable.' VALUES(';
                
                for($j=0; $j<$num; $j++) {
                    $row[$j] = addslashes($row[$j]);$row[$j] = preg_replace("/\n/","\\n",$row[$j]);
                    if(isset($row[$j]))
                        $salida .= '"'.$row[$j].'"';
                        
                    else
                        $salida .= '""';
                        
                    if($j < ($num-1))  
                        $salida .= ',';
                }
                    
                $salida .= ");\n"; 
            }
                
            $salida .= "\n\n\n";

        }

        return $salida;
    }

    /*------------------------------------------------------------- FUNCIÓN PARA AÑADIR UNA NOTA A UNA RECETA-----------------------------------------*/
    function addRate($recipe,$user,$rate){
        $mysqli = connectDB();

        $insertCommand = $mysqli->prepare("INSERT INTO valoraciones(idreceta,idusuario,valoracion) VALUES(?,?,?)");

        $insertCommand->bind_param("iii",$recipe,$user,$rate);
        $insertCommand->execute();
        $insertCommand->close();


    }

    /*------------------------------------------------------------ FUNCIÓN PARA VER SI EL USUARIO HA VALORADO O NO LA RECETA-------------------*/
    function recipeRated($userID,$recipeID){
        $mysqli = connectDB();
        $rated = 0;

            $selectCommand = $mysqli->prepare("SELECT valoracion FROM valoraciones WHERE idreceta=? and idusuario=?");
            $selectCommand->bind_param("ii",$recipeID,$userID);
            $selectCommand->execute();

            $getInfo = $selectCommand->get_result();
            
            if($getInfo->num_rows > 0){
                $rated = 1;
            }

            $selectCommand->close();

        return $rated;
    }

    /*------------------------------------------------ FUNCIÓN PARA ELIMINAR LA BASE DE DATOS -----------------------------------------*/
    function deleteDB($adminID){
        $mysqli = connectDB();
        /*Procedemos a borrar las tablas*/

            /*Primero, las tablas que dependen de otras*/
            $deleteCommand = $mysqli->query("DELETE FROM valoraciones");
            $deleteCommand = $mysqli->query("DELETE FROM fotos");
            $deleteCommand = $mysqli->query("DELETE FROM logging");
            $deleteCommand = $mysqli->query("DELETE FROM comentarios");
            $deleteCommand = $mysqli->query("DELETE FROM categorias");

            /*Luego, las tablas de las que dependía una de las anteriores*/
            $deleteCommand = $mysqli->query("DELETE FROM listacategorias");
            $deleteCommand = $mysqli->query("DELETE FROM recetas");

            /*Por último, la tabla de usuarios*/
            $deleteCommand = $mysqli->prepare("DELETE FROM usuarios WHERE id!=? and nombre !='Anónimo'");
            $deleteCommand->bind_param("i",$adminID);
            $deleteCommand->execute();
            $deleteCommand->close();
    }

    /*----------------------------------------------- FUNCIÓN PARA RESTAURAR LA BASE DE DATOS -----------------------------------------------*/
    function restoreDB($fileName){
        $mysqli = connectDB();

        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        $result = $mysqli->query('SHOW TABLES');
        
        while($row= mysqli_fetch_row($result))
            $mysqli->query('DELETE * FROM '.$row[0]);
            
        $error = '';
        $sql = file_get_contents($fileName);
        $queries= explode(';',$sql  );

        foreach($queries as $q) {
            if(!$mysqli->query($q))
                $error .= $mysqli->error;
        }

        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    }

    /*------------------------------------------- FUNCIÓN PARA VER SI LA BASE DA DATOS ESTÁ VACÍA --------------------------------------------*/
    function dataBaseEmpty(){
        $mysqli = connectDB();
        $result = 0;

        $show = $mysqli->query("SHOW TABLES FROM sergirusan1920");

        if($show->num_rows > 0){
            $result = 0;
        }

        else{
            $result = 1;
        }

        return $result;
    }

    /*-------------------------------------------- FUNCIÓN PARA CREARNOS NUESTRO PROPIO USUARIO -----------------------------------------------*/
    function createMyOwnUser($name,$surname,$email,$password){
        $mysqli = connectDB();
        /*primero, codificamos la contraseña*/
        $password = password_hash($password,PASSWORD_DEFAULT);

        /*Creamos el nombre para la foto*/
        $userNameAux = str_replace(" ","",$name);
        $userSurnameAux = str_replace(" ","",$surname);

        $photoOriginalName = $userNameAux.$userSurnameAux.".jpg";

        /*Inesrtamos el usuario en la lista*/
            $insertCommand = $mysqli->prepare("INSERT INTO usuarios(nombre,apellidos,email,contraseña,foto,tipo) VALUES (?,?,?,?,?,'pendiente')");
            $insertCommand->bind_param("sssss",$name,$surname,$email,$password,$photoOriginalName);
            $insertCommand->execute();
            $insertCommand->close();


        /*Último, ponemos una imagen por defecto para el usuario*/
        copy("usersProfiles/newUser.jpg","usersProfiles/".$photoOriginalName);

    }

?>