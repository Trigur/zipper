<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Zipper_model extends CI_Model
{
    private $COMPONENT_NAME = 'zipper';
    private $TABLE_NAME = 'zipper';

    public function __construct()
    {
        parent::__construct();
    }

    public function getByHash($hash)
    {
        $queryResult = $this->db->get_where($this->TABLE_NAME, ['hash' => $hash])->row_array();

        return $queryResult ? $queryResult : false;
    }

    public function insert($data)
    {
        $this->db->insert($this->TABLE_NAME, $data);

        $row = $this->db->query('SELECT LAST_INSERT_ID()')->row_array();
        $LastIdInserted = $row['LAST_INSERT_ID()'];

        return $LastIdInserted;
    }

    public function remove($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->delete($this->TABLE_NAME);
    }

    public function install()
    {
        $this->load->dbforge();

        $fields = [
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => TRUE
            ],

            'hash'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 50],
            'relativePath' => ['type' => 'VARCHAR', 'constraint' => 500],
        ];

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_field($fields);
        $this->dbforge->create_table($this->TABLE_NAME, TRUE);

        $this->db
            ->where('name', $this->COMPONENT_NAME)
            ->update('components', [
                'autoload' => '0',
                'enabled'  => '0',
                'in_menu'  => '0'
            ]);
    }

    public function errorInstall()
    {
        $this->db->where('name', $this->COMPONENT_NAME);
        $this->db->delete('components');
    }

    public function deinstall()
    {
        $this->load->dbforge();
        $this->dbforge->drop_table($this->TABLE_NAME);
    }
}
