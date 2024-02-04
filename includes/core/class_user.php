<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        $empty_request = [
            'id' => 0,
            'access' => 0,
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'email' => '',
            'plot_id' => '',
        ];
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return $empty_request;
        
        // info
        $q = DB::query("SELECT * FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'access' => (int) $row['access'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => phone_formatting($row['phone']),
                'email' => $row['email'],
            ];
        } else {
            return $empty_request;
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }
    
    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) $where[] = "first_name LIKE '%".$search."%' OR email LIKE '%".$search."%' OR phone LIKE '%".$search."%'";
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
            FROM users ".$where." ORDER BY user_id+0 LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => phone_formatting($row['phone']),
                'email' => $row['email'],
                'last_login' => (int) $row['last_login']
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }
    
    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }
    
    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info(['user_id' => $user_id]));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }
    
    public static function user_delete_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
    
        if ($user_id) {
            $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
            
            $q = DB::query("DELETE FROM users WHERE user_id='" . $user_id . "';") or die (DB::error());
            DB::fetch_row($q);
    
            return User::users_fetch(['offset' => $offset]);
        }
    }
    
    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $plots_id = 0;
        
        if (isset($d['plot_id']) && trim($d['plot_id'])) {
            $plots_id = explode(',', trim($d['plot_id']));
            $plots_id_numeric = [];
            
            foreach ($plots_id as $id) {
                $id = trim($id);
                if (is_numeric($id)) {
                    array_push($plots_id_numeric, trim($id));
                }
            }
            
            $plots_id = implode(',', array_unique($plots_id_numeric));
        }
        
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
        $phone = isset($d['phone']) && is_numeric($d['phone']) ? $d['phone'] : 0;
        $phone_code = 1111;
        $email = isset($d['email']) && trim($d['email']) ? strtolower(trim($d['email'])) : '';
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        
        $data_check = [
            isset($d['first_name']) && trim($d['first_name']),
            isset($d['last_name']) && trim($d['last_name']),
            isset($d['phone']) && trim($d['phone']),
            isset($d['email']) && trim($d['email']),
        ];
        
        $data_not_empty = true;
        
        foreach (array_unique($data_check) as $item) {
            if (!$item) {
                $data_not_empty = $item;
            }
        }
        
        if ($data_not_empty) {
            // update
            if ($user_id) {
                $set = [];
                $set[] = "plot_id='".$plots_id."'";
                $set[] = "first_name='".$first_name."'";
                $set[] = "last_name='".$last_name."'";
                $set[] = "phone='".$phone."'";
                $set[] = "phone_code='".$phone_code."'";
                $set[] = "email='".$email."'";
                $set[] = "updated='".Session::$ts."'";
                $set = implode(", ", $set);
                DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
            } else {
                DB::query("INSERT INTO users (
                plot_id,
                first_name,
                last_name,
                phone,
                phone_code,
                email,
                last_login
            ) VALUES (
                '".$plots_id."',
                '".$first_name."',
                '".$last_name."',
                '".$phone."',
                '".$phone_code."',
                '".$email."',
                '".Session::$ts."'
            );") or die (DB::error());
            }
    
            // output
            return User::users_fetch(['offset' => $offset]);
        }
    }
}
