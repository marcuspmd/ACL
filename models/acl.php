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
        // Configure here your user id session.
        $ACL_USER = $this->session->userdata('id_user');

        if ($ACL_USER == '') {
            $this->session->flashdata('error', 'User is not logged.');
            redirect($this->page_redirect);
            exit;
        } else {
            $white_list = array();
            $white_list[] = 'validation';
            $url = $this->uri->uri_string();
            ($this->uri->total_segments() == 2)? $url.'/' : $url;

            if ($this->validate_white_list($url))
              return TRUE;

            if ($this->validate_permission($ACL_USER, $url) === false){
                $this->session->flashdata('error', 'User don\'t has permission.');
                redirect($this->page_redirect);
                exit;
            }
            return true;
        }
    }


    private function validate_white_list($url){
        $this->db->select('routine.link');
        $this->db->where('routine.link', $url);
        $this->db->where('routine.whitelist', 1);
        $query = $this->db->get('routine');
            if ($query->num_rows == 0) {
                return false;
            }
        return true;
    }

    private function validate_permission($ACL_USER, $url){
        $this->db->select('routine.link');
        $this->db->join('permission', 'routine.id_routine = permission.routine_id_routine');
        $this->db->where('routine.link', $url);
        $this->db->where('permission.user_id_user', $ACL_USER);
        $this->db->where('routine.whitelist', 0);
        $query = $this->db->get('routine');
            if ($query->num_rows == 0) {
                return false;
            }
        return true;
    }

}

