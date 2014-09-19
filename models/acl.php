<?php

class acl extends CI_Model {

    public $page_redirect;
    private $CI;

    public function __construct() {
        parent::__construct();
        $this->CI = & get_instance();
        $this->page_redirect = base_url();
    }


    public function permission_validation() {
        $ACL_USER = $this->session->userdata('id_user');

        if ($ACL_USER == '') {
            redirect($this->page_redirect);
        } else {
            $white_list = array();
            $white_list[] = 'validation';
            $url = $this->uri->uri_string();

            if (in_array($url, $white_list)) {
                return TRUE;
            }

            $this->db->select('module.description as module_name, routine.name as menu_nome, routine.access_key, routine.link');
            $this->db->join('routine', 'module.id_module = routine.module_id_module');
            $this->db->join('permission', 'routine.id_routine = permission.routine_id_routine');
            $this->db->where("'$url'".' regexp ', 'routine.link', false);
            $this->db->where('permission.user_id_user', $ACL_USER);
            $query = $this->db->get('module');
            if ($query->num_rows == 0) {
                $this->session->set_flashdata('msg', 'Seu usuário atual não possui permissões para acessar a página solicitada.');
                redirect($this->page_redirect);
            }

            return true;
        }
    }

}
