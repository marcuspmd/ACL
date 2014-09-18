<?php

class controle_acesso extends CI_Model {

    public $user_permission;
    public $system_permission;
    public $html_menu;
    public $html_atalho;
    public $page_redirect;
    private $released_ip = 'localhost';
    private $CI;
    private $without_validation;

    public function __construct() {
        parent::__construct();

        $this->CI = & get_instance();
        $this->page_redirect = base_url();
        $this->without_validation = array(
            'home/cadastro',
            'home/cadastrar',
            'home/verify_login'
        );
    }

    public function user_permission($id_user = 0) {
        return true;
        // Caso id_user nao seja setado, por padrao define o que se encotnra no post,
        // isso foi feito para reaproveitar codigo, uma vez que essa funcao tambem e utilizada
        // no cadastro de permissoes do sistema
        $id_user = (!empty($id_user)) ? $id_user : $this->session->userdata('id_user');
        $where['user_id_user ='] = $id_user;

        $this->db->select('permission.routine_id_routine, routine.type');
        $this->db->join('routine', 'permission.routine_id_routine = routine.id_routine');
        $query = $this->db->get_where('permission', $where)->result_array();
        $array = array();
        foreach ($query as $k => $v) {
            $array[$v['routine_id_routine']] = TRUE;
        }
        return $array;
    }

    public function system_permission() {
        // return true;
        // Pega as user_permission que o usuario atual tem acesso
        $array = '';
        $this->db->distinct();
        $this->db->select('module.linkAcess as link, permission.module_id_module as modulo_link, module.description, module.icon');
        $this->db->join('permission', 'permission.module_id_module = module.id_module');
        // $this->db->where('routine.menu', 1);
        $this->db->order_by('module.order', 'asc');

        // ------------- CONTROLE DE HIERARQUIA --------------------
        // O sistema so puxa TODAS permissoes do sistema caso o usuario logado seja do type 6 (master)
        // caso contrario, ele so pode dar permissoes que ele possua, nessa etapa o sistema de HIERARQUIA comeca a funcionar
        if ($this->session->userdata('type') < 5) {
            $this->db->where('permission.user_id_user', $this->session->userdata('id_user'));
        }
        // ---------------- FIM DO CONTROLE -----------------------
        // Carrega os dados
        $query = $this->db->get('module')->result_array();
        $access_key = '';
        // var_dump($query);
        // exit;
        // Constroi o array fazendo com que os itens tornem se filhos do registro pai,
        // a varivel user_permission e instanciada no arquivo index.php
        foreach ($query as $key => $val) {
            $k = $val['modulo_link'];
            // Cria o array apenas para os itens que nao possum um id_routine_pai,
            // para os que possuem, eles serao adicionados a outros registros
            $array[$k] = array(
                'name' => $val['description'],
                'icon' => $val['icon'],
                'link' => $val['link']
                );
        }

        return $array;
    }

    public function project_permission(){
        return true;
        $array = '';
        $this->db->distinct();
        $this->db->select('unit_negociation.id_unit_negociation, unit_negociation.name, unit_negociation.interface, unit_negociation.icon');
        $this->db->join('un_has_user', 'unit_negociation.id_unit_negociation = un_has_user.id_unit_negociation and un_has_user.id_user ='.$this->session->userdata('id_user'));
        $query = $this->db->get('unit_negociation')->result_array();

        $project = '';
        foreach ($query as $key) {
            if ($key['interface'] == $this->session->userdata('interface')){
                $add = 'href="javascript:void(0);" style="background: #1c3143"';
            }else{
                $add = 'href="'.base_url().'home/change_un/'.$key['interface'].'"';
            }

            $project .= '<li>
            <a '.$add.'>
                <span class="image"><i class="'.$key['icon'].'"></i></span>
                <span class="title">'.$key['name'].'</span>
            </a>
        </li>';

        $add = '';
        }

       return $project;
    }

    public function change_un($un){
        if (empty($un)) return false;

        $this->db->select('un_has_user.id_unit_negociation, unit_negociation.name');
        $this->db->join('unit_negociation', 'unit_negociation.id_unit_negociation = un_has_user.id_unit_negociation');
        $this->db->where('id_user', $this->session->userdata('id_user'));
        $this->db->where('un_has_user.id_unit_negociation', $un);
        $query = $this->db->get('un_has_user');
        if ($query->num_rows){
            $query = $query->row_array();
            $this->session->set_userdata('interface', $un);
            $this->session->set_userdata('un_name', $query['name']);
            return true;
        }
        return false;
    }

    public function html_menu() {
       $links_gerados = '';
       foreach ($this->system_permission as $menu) {

        $links_gerados .= '
                            <li>
                                <a href="'.base_url().$menu['link'].'">
                                    <i class="'.$menu['icon'].'"></i>
                                    '.$menu['name'].'
                                </a>
                            </li>';
        }

        return $links_gerados;
    }



public function permission_validation() {
// if ((stristr($_SERVER["HTTP_HOST"], 'localhost') === FALSE)) {
    //     return true;
    // }

    if (in_array($this->uri->segment(2).'/'.$this->uri->segment(3), $this->without_validation))
    {
        return TRUE;
    }
        // if((stristr($_SERVER["HTTP_HOST"], 'sispage.com.br') === FALSE)and(stristr($_SERVER["HTTP_HOST"], 'localhost') === FALSE)) {
        //     return true;
        // }
    $this->user_permission = $this->user_permission();
    $this->system_permission = $this->system_permission();

        // Seta as configuracoes no CI para que elas possam ser acessiveis dentro do Layout.php dentro de hooks,
        // evitando que o sistema efetue queryes desnecessarias
    $this->CI->controle_acesso->user_permission = $this->user_permission;
    $this->CI->controle_acesso->system_permission = $this->system_permission;


        // Antes de efetuar qualquer validacao da url do usuario, valida
        // primeiramente se o horario de acesso do mesmo e permitido
    if ($this->validate_time($this->session->userdata('access_time_entry'), $this->session->userdata('access_time_output')) == FALSE) {
        $this->session->set_flashdata('msg', 'Seu usuário não está permitido para acessar nosso sistema nesse horário.');
        // redirect(base_url());
    }


        // Valida se a pagina que o usuario esta tentando acessar e a de upload, e permite o acesso a ela
    $list = array();
    $list[] = '';
    $list[] = 'arquivos';
// $list[] = 'automacao';
    $list[] = 'youtube';
    $list[] = 'home';
    $list[] = 'sistema';
    $list[] = 'automacao';
    $list[] = 'usuario';
    $list[] = 'site';
    $list[] = 'create';
    $list[] = 'template';
    $list[] = 'plano';
    $list[] = 'validation';

    if (in_array($this->uri->segment(1), $list)) {
        $this->CI->controle_acesso->html_menu = $this->html_menu();
        $this->CI->controle_acesso->html_atalho = $this->html_atalho;
        return TRUE;
    }

        // Valida se a sessao do usuario esta setada
    if ($this->session->userdata('id_user') == '') {
        redirect(base_url());
    } else {
            // _search sera um padrao especifico para as urls de ajax de pesquisa do sistema, sempre que
            // este padrao for encotrado na url o sistema liberara o acesso a pagina
        if (strstr($this->uri->segment(3), 'search')) {
            return TRUE;
        }

            // Valida se o link atual se encontra na whitelist de acesso, ou seja, nao e necessario validacao
        $white_list = array();
        $white_list[] = 'grid';
        $white_list[] = 'perfil';
        $white_list[] = 'config';
        $white_list[] = 'get_image_video';
        $white_list[] = 'plano';
        $white_list[] = 'change_un';
        $white_list[] = 'escolher_layout';
        $white_list[] = 'adicionarDominio';
        $white_list[] = 'upload';
        $white_list[] = 'deletarDominio';
        $white_list[] = 'config_google';
        $white_list[] = 'get_data';
        $white_list[] = 'apply_accounts';
        $white_list[] = 'validation';

        if (in_array($this->uri->segment(2), $white_list) || in_array($this->uri->segment(3), $white_list)) {
            $this->CI->controle_acesso->html_menu = $this->html_menu();
            $this->CI->controle_acesso->html_atalho = $this->html_atalho;
            return TRUE;
        }

            // Monta os segmentos ate a terceira url
        $url = '';
        $url .= ($this->uri->segment(1)) ? $this->uri->segment(1) . '/' : '';
        $url .= ($this->uri->segment(2)) ? $this->uri->segment(2) . '/' : '';
        $url .= ($this->uri->segment(3)) ? $this->uri->segment(3) : '';

            // Valida se o usuario tem acesso ao atual link
        $this->db->select('module.description as module_name, routine.name as menu_nome, routine.access_key, routine.link');
        $this->db->join('routine', 'module.id_module = routine.module_id_module');
        $this->db->join('permission', 'routine.id_routine = permission.routine_id_routine');
        $this->db->where("'$url'".' regexp ', 'routine.link', false);
        $query = $this->db->get('module');
        if ($query->num_rows == 0) {

                  //verificar se tem plano escolhido


            $this->session->set_flashdata('msg', 'Seu usuário atual não possui permissões para acessar a página solicitada.');
            redirect($this->page_redirect);


        } 

        $this->CI->controle_acesso->html_menu = $this->html_menu();
        $this->CI->controle_acesso->html_atalho = $this->html_atalho;
    }
}

public function login_validation() {
    if ($this->session->userdata('id_user') == '') {

        // Caso os posts de login e senha estejam diferente de vazio, o sistema efetua a
        // validacao deles para que o usuario possa logar no sistema
        if ($this->input->post('login') != '' && $this->input->post('password') != '') {
            $where['email'] = $this->input->post('login');
            $where['password'] = encript_password($this->input->post('password'));
            $this->db->distinct();
            $this->db->order_by('user.id_user asc');
            $this->db->limit(1);
            $this->db->select('user.id_user, user.enabled, user.type,  user.name, user.email');
            $query = $this->db->get_where('user', $where);
            if ($query->num_rows != 1) {
                $this->session->set_flashdata('msg', 'Usuário ou senha incorretos.');
                return FALSE;
            }

            $query = $query->row_array();
            if (!$query['enabled']){
                $this->session->set_flashdata('msg', 'Usuário desativado.');
            }
            // Seta as variaveis do usuario no sistema
            $this->session->set_userdata('id_user', $query['id_user']);
            $this->session->set_userdata('name', $query['name']);
            $this->session->set_userdata('email', $query['email']);
            $this->session->set_userdata('type', $query['type']);
            return TRUE;
        }else{
            $this->session->set_flashdata('msg', 'Favor logar no sistema.');
            return false;
        }
    }else{
        return TRUE;
    }

    return FALSE;
}

    /*
     * O metodo valida ip e responsavel por checar as configuracoes da unidade de negocio e tambem
     * por fazer a validacao do ip local com o ip que o usuario ira acessar, deste modo o sistema
     * tem seus dados protegidos de terceiros que estejam fora da empresa. $this->released_ip e um parametro
     * configuravel que sera gravado no module de permissoes
     */

    private function ip_validation() {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (is_numeric($this->released_ip)) {
            if ($ip != $this->released_ip) {
                return FALSE;
            }
        } else {
            if ($ip != gethostbyname($this->released_ip)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /*
     * O metodo acesso multiplo e responsavel por validar se o usuario que deseja
     * logar no sistema ja esta logado no sistema, e caso esteja, dirruba a atual
     * instancia, para que este novo usuario possa se conectar
     */

    private function multiple_access_validation($id_user) {
        $this->db->select('id_user');
        $this->db->where('id_user', $id_user);
        $query = $this->db->get('ci_sessions');

        if ($query->num_rows == 0) {
            return TRUE;
        } else {
            $this->db->where('id_user', $id_user);
            $this->db->delete('ci_sessions');
        }
        return TRUE;
    }


    private function validate_time($entry = '00:00:00', $output = '00:00:00') {
        $hora_atual = (date('H:i') . ':00');
        if ((!empty($entry) && !empty($output)) && ($entry != '00:00:00' && $output != '00:00:00')) {            
            $entry = trim($entry);
            $output = trim($output);
            if ($hora_atual < ($entry) || $hora_atual > ($output)) {
                return FALSE;
            }
        }
        return TRUE;
    }

}