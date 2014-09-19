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
            redirect($this->page_redirect);
        } else {
            $white_list = array();
            $white_list[] = 'validation';
            $url = '';
            $url .= ($this->uri->segment(1)) ? $this->uri->segment(1) . '/' : '';
            $url .= ($this->uri->segment(2)) ? $this->uri->segment(2) . '/' : '';
            $url .= ($this->uri->segment(3)) ? $this->uri->segment(3) : '';

            if ($this->validate_white_list($url))
              return TRUE;

            return $this->validate_permission($ACL_USER, $url);
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