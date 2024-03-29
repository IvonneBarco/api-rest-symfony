<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;


use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;

class UserController extends AbstractController{    
    
    private function resjson($data){

        //Serializar datos con servicio serializer
        $json = $this->get('serializer')->serialize($data, 'json');

        //Response con httpfoundation
        $response = new Response();

        //Asignar contenido a la respuesta
        $response->setContent($json);

        //Indicar el formato de la respuesta
        $response->headers->set('Content-Type', 'application/json');

        //Devolver la respuesta
        return $response;
    }
    public function index(){

        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $video_repo = $this->getDoctrine()->getRepository(Video::class);

        $users = $user_repo->findAll();
        $user = $user_repo ->find(1);

        $videos = $video_repo->findAll();
        $video = $video_repo ->find(1);

        $data = [
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ];

        // foreach ($users as $user) {
        //     echo "<h1> {$user->getName() } {$user->getSurname() } </h1>";
            
        //     foreach ($user->getVideos() as $video) {
        //         echo "<p> {$video->getTitle() } - {$video->getUser()->getEmail() } </p>";
        //     }
        // }



        return $this->resjson($data);
    }

    public function create(Request $request){
        //Recoger los datos por post
        $json = $request->get('json', null);

        //Decodificar el json 
        $params = json_decode($json);

        //Respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha creado',
            'json' => $params
        ];

        //Comprobar y validar datos
        if($json != null){
            
            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
        
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && count($validate_email) == 0 && !empty($password) && !empty($name) && !empty($surname)){
                
                //Si la validación es correcta, crear el objeto del usuario
                $user = new User();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setPassword($password);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new \DateTime('now'));

                //Cifrar la contraseña
                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);

                $data = $user;

                //Comprobar si el usuario existe (duplicado)
                $doctrine = $this->getDoctrine();
                $em = $doctrine->getManager();

                $user_repo = $doctrine->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));

            
                // Si no existe, guardo el usuario
                if(count($isset_user) == 0){

                    //guardo el usuario
                    $em->persist($user);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'Usuario creado con éxito',
                        'json' => $params
                    ];

                }else{

                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El usuario ya existe',
                        'json' => $params
                    ];
                }

                


                //Si no existe, guardarlo en la bd

            }
        }

        

        //Hacer respuesta en json
        // return new JsonResponse($data);
        return $this->resjson($data);
    }

    public function login(Request $request, JwtAuth $jwt_auth){

        //Recibir los datos por post
        $json = $request->get('json', null);
        $params = json_decode($json);

        //Array por defecto para devolver
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha podido identificar'
        ];

        //Comprobar y validar datos
        if($json != null){

            $email = (!empty($params->email)) ? $params->email: null;
            $password = (!empty($params->password)) ? $params->password: null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken: null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && !empty($password) && count($validate_email) == 0){
                
                //Cifrar la contraseña
                $pwd = hash('sha256', $password);

                //Si todo es valido, llamaremos a un servicio para identificar al usuario (jwt, o un objeto)
                //Crear servicio jwt
                
                if($gettoken){
                    $signup = $jwt_auth->signup($email, $pwd, $gettoken);
                }else{
                    $signup = $jwt_auth->signup($email, $pwd);
                }

                return new JsonResponse($signup);
            }


        }

        
                //Si nos devuelve bien los datos, respuesta

        return $this->resjson($data);
    }

    public function edit(Request $request, JwtAuth $jwt_auth){
        
        //Recoger la cabecera de autenticación
        $token = $request->headers->get('Authorization');

        //Crear un metodo para comprobar si el token es correcto
        $authCheck = $jwt_auth->checkToken($token);

        //Respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Usuario no actualizado'
        ];

        //Si es correcto, hacer la actualización del usuario
        if ($authCheck) {
            //Actualizar al usuario

            //Conseguir entity manager
            $em = $this->getDoctrine()->getManager();

            //Conseguir los datos del usuario identificado
            $identity = $authCheck = $jwt_auth->checkToken($token, true);

            //Conseguir el usuario actualizar completo
            $user_repo = $this->getDoctrine()->getRepository(User::class);
            $user = $user_repo->findOneBy([
                'id' => $identity->sub
            ]);

            //Recoger los datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            //Comprobar y validar los datos
            if(!empty($json)){

                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->name)) ? $params->surname : null;
                $email = (!empty($params->name)) ? $params->email : null;
            
                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);

                if(!empty($email) && count($validate_email) == 0 && !empty($name) && !empty($surname)){

                    //Asignar nuevos datos al objeto del usuario
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setSurname($surname);

                    //Comprobar duplicados
                    $isset_user = $user_repo->findBy([
                        'email'=> $email
                    ]);

                    if(count($isset_user) == 0 || $identity->email == $email){
                        //Guardar cambios en la base de datos
                        $em->persist($user);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Usuario actualizado',
                            'user' => $user
                        ];

                    }else{
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'No puedes usar ese email, usuario duplicado',
                            'user' => $user
                        ];
                    }

                }
            }
        }

        //...

        

        return $this ->resjson($data);        
    }

    
}
