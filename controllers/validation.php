<?php


/*

Table of Routines

CREATE TABLE `routine` (
  `id_routine` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `description` text,
  `name` varchar(100) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `module_id_module` int(10) unsigned DEFAULT NULL,
  `whitelist` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_routine`),
  UNIQUE KEY `link_UNIQUE` (`link`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci'


Table of Modules

CREATE TABLE `module` (
  `id_module` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_module`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';

Table of permission

CREATE TABLE `permission` (
  `id_permission` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `routine_id_routine` int(10) unsigned NOT NULL,
  `user_id_user` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_permission`),
  KEY `fk_permissao_rotina1` (`routine_id_routine`),
  KEY `fk_permissao_usuario1` (`user_id_user`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';


*/

class validation extends CI_Controller {

    public $layout = 'blank';

    public function index() {
        $directoryList = FCPATH.'application/controllers/';
        $directory = new \RecursiveDirectoryIterator($directoryList);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $fileinfo) {
            $folder = '';
              if ($fileinfo->isFile()) {
                    if (stripos($fileinfo, '.php')!== false){
                        include_once($fileinfo);

                        $dir = str_replace(array($directoryList,'.php'), '', $fileinfo->getRealPath());
                        

                        if (stripos($dir, "/")!== false){
                            list($folder, $filenameFile) = explode('/', $dir);
                        }

                        $class = str_replace('.php', '', $fileinfo->getFilename());
                        $classes[$class]['class'] = new ReflectionClass($class);
                        $classes[$class]['folder'] = $folder;
                    }
                }
        }
        
        $perm_of_database = $this->get_db_routines();
        foreach($perm_of_database as $key){
            $listPermission[$key['link']] = ($key['whitelist'] == 1)? '1' : '2';
        }


        foreach ($classes as $key=> $value){
            $class_methods = $value['class']->getMethods(ReflectionMethod::IS_PUBLIC);
                $i = 0;
            foreach ($class_methods as $method_name) {
                $checked_whitelist = ''; $checked_required = '';
                if ($method_name->class != 'CI_Controller'){
                    $acl_url = $this->get_url($value['folder'], $method_name->class, $method_name->name);

                    if (array_key_exists($acl_url, $listPermission)){
                        if ($listPermission[$acl_url] == 1){
                            $checked_whitelist = 'checked="checked"';
                        }elseif ($listPermission[$acl_url] == 2){
                            $checked_required = 'checked="checked"';
                        }
                    }

                    $listing[$method_name->class][$i]['folder'] = $value['folder'];
                    $listing[$method_name->class][$i]['class'] = $method_name->class;
                    $listing[$method_name->class][$i]['function'] = $method_name->name;
                    $listing[$method_name->class][$i]['whitelist'] = '<input type="radio" '.$checked_whitelist.' name="'.$acl_url.'" value="1" >';
                    $listing[$method_name->class][$i]['required'] = '<input type="radio" '.$checked_required.' name="'.$acl_url.'" value="2" >';
                    $i++;
                }
            }
        }
        $data['listing'] = $listing;
    
        $this->load->view('validation/validation', $data);

    }

    public function pop_routine(){
        $i=0;
        foreach($_POST as $key => $value){
            $data[$i]['link'] = $key;
            $data[$i]['whitelist'] = ($value == 1)? $value : '0';
            $i++;
        }
        $this->save_db_routines($data);
        redirect('validation/user_permission');
    }

    public function user_permission($user = null){
        if ($user == null){
            $user = $_POST['user'];
        }
        $data['user_selected'] = $user;

        $perm_of_database = $this->get_db_routines(1);
        $i = 0;
        foreach($perm_of_database as $key){
            if ($this->user_db_permission($key['id_routine'],$user)){
                $check_permission = 'checked="checked"';
            }else{
                $check_permission = '';
            }
            $url = $this->remake_url($key['link']);
            $listPermission[$i] = $url;
            $listPermission[$i]['permission'] = '<input type="checkbox" '.$check_permission.' name="permission[]" value="'.$key['id_routine'].'" >';
            $i++;
        }
        $data['listPermission'] =  $listPermission;
        $data['user'] = '<input type="hidden" name="user" value="'.$user.'" />';

        $this->load->view('validation/userPermission', $data);     
    }

    public function finish_permission(){
        if (empty($_POST['user'])){
            echo 'Usuario não selecionado.';
            exit;
        }

        $this->save_db_permissions($_POST);
        $this->load->view('validation/finish');

    }

    private function get_url($folder = '', $class, $function){
        $url = '';
        $url .= (!empty($folder))? $folder.'/' : '';
        $url .= (!empty($class))? $class.'/' : '';
        $url .= (!empty($function))? $function : '';
        $url .= (!empty($folder))? '' : '/';

        return mb_strtolower($url);
    }

    private function remake_url($url){
        $list = explode('/', $url);
        if (empty(end($list))){
           $return['folder'] = '';
           $return['class'] = $list[0];
           $return['function'] = $list[1];
        }else{
           $return['folder'] = $list[0];
           $return['class'] = $list[1];
           $return['function'] = $list[2];
        }   
        
        return $return;

    }


    //database function

    private function get_db_routines($required = null){
        $this->db->select('id_routine, link, whitelist');
        if ($required)
            $this->db->where('whitelist', 0);

        $this->db->order_by('link', asc);

        return $this->db->get('routine')->result_array();
    }

    private function save_db_routines($data){
        foreach ($data as $key){
            $sql = 'INSERT INTO routine (link, whitelist) VALUES("'.$key['link'].'", '.$key['whitelist'].') ON DUPLICATE KEY UPDATE whitelist='.$key['whitelist'];
            $this->db->query($sql);
        }
    }

    private function user_db_permission($id, $user){
        $this->db->select('id_permission');
        $this->db->where('routine_id_routine', $id);
        $this->db->where('user_id_user', $user);
        $query = $this->db->get('permission');
        return $query->num_rows > 0;
    }

    private function save_db_permissions($data){
        $user = $data['user'];

        $this->db->where('user_id_user', $user);
        $this->db->delete('permission');

        foreach ($data['permission'] as $key){
            $this->db->set('user_id_user', $user);
            $this->db->set('routine_id_routine', $key);
            $this->db->insert('permission');
        }

    }

}