<?php
class Widgets_model extends CI_Model
{
    public function get_all()
    {
        return $this->db->order_by('LOWER(name)', 'ASC', FALSE)->get('widgets')->result();
    }

    public function get($id)
    {
        return $this->db->get_where('widgets', ['id' => $id])->row();
    }

    public function insert($data)
    {
        $this->db->insert('widgets', $data);
    }

    public function update($id, $data)
    {
        $this->db->where('id', $id)->update('widgets', $data);
    }

    public function delete($id)
    {
        $this->db->delete('widgets', ['id' => $id]);
    }
}
