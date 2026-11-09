<?php

/**
 * Face templates (128-number descriptors computed in the browser) for
 * teachers, admins and staff. Only numbers are stored, never photos.
 */
class FaceModel extends Model
{
    public function ensure_table()
    {
        $this->db->raw(
            'CREATE TABLE IF NOT EXISTS `face_templates` (
                `user_id` INT NOT NULL,
                `descriptors` MEDIUMTEXT NOT NULL,
                `sample_count` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function all_templates()
    {
        $rows = $this->db->table('face_templates')->get_all();

        return is_array($rows) ? $rows : [];
    }

    public function template_for_user($user_id)
    {
        $row = $this->db->table('face_templates')
            ->where('user_id', (int) $user_id)
            ->get();

        return is_array($row) ? $row : null;
    }

    public function save_template($user_id, array $descriptors, $now)
    {
        $json = json_encode($descriptors);

        if ($this->template_for_user($user_id)) {
            return $this->db->table('face_templates')
                ->where('user_id', (int) $user_id)
                ->update([
                    'descriptors' => $json,
                    'sample_count' => count($descriptors),
                    'updated_at' => $now,
                ]);
        }

        return $this->db->table('face_templates')->insert([
            'user_id' => (int) $user_id,
            'descriptors' => $json,
            'sample_count' => count($descriptors),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function delete_template($user_id)
    {
        return $this->db->table('face_templates')
            ->where('user_id', (int) $user_id)
            ->delete();
    }

    /** Latest gate event of a person (same table the RFID gate uses). */
    public function last_gate_event($user_id)
    {
        $rows = $this->db->table('rfid_gate_logs')
            ->where('user_id', (int) $user_id)
            ->order_by('scanned_at', 'DESC')
            ->limit(1)
            ->get_all();

        return is_array($rows) && isset($rows[0]) ? $rows[0] : null;
    }

    public function add_gate_event(array $fields)
    {
        return $this->db->table('rfid_gate_logs')->insert($fields);
    }
}
