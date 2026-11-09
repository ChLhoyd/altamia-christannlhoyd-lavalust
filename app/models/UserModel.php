<?php

class UserModel extends Model
{
    protected $table = 'users';
    protected $primary_key = 'user_id';

    public function find_by($column, $value)
    {
        if (!in_array($column, ['email', 'user_id'], true)) {
            return null;
        }

        $row = $this->db
            ->table($this->table)
            ->where($column, $value)
            ->get();

        // Soft-deleted accounts behave as if they no longer exist
        // (they cannot sign in and are hidden from normal lists).
        if (is_array($row) && !empty($row['deleted_at'])) {
            return null;
        }

        return $row;
    }

    /**
     * Same lookup as find_by(), but also returns soft-deleted accounts.
     * Used for restoring accounts and for e-mail uniqueness checks.
     */
    public function find_any($column, $value)
    {
        if (!in_array($column, ['email', 'user_id'], true)) {
            return null;
        }

        return $this->db
            ->table($this->table)
            ->where($column, $value)
            ->get();
    }
}