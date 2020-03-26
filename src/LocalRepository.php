<?php


namespace YiluTech\Permission;


use YiluTech\Permission\Models\Permission;

class LocalRepository extends StoreAbstract
{
    public function getLastUpdateTime()
    {
        return Permission::query($this->serverScopeName())->max('updated_at');
    }

    public function saveChanges($changes)
    {
        if (empty($changes)) {
            return 0;
        }
        return \DB::transaction(function () use ($changes) {
            foreach ($changes as $name => $change) {
                if (!empty($change['data'])) {
                    $change['data'] = array_map(function ($data) {
                        return is_array($data) ? json_encode($data) : $data;
                    }, $change['data']);
                    $change['data']['updated_at'] = $change['date'];
                }
                switch ($change['action']) {
                    case 'CREATE':
                        $change['data']['created_at'] = $change['date'];
                        \DB::table('permissions')->insert($change['data']);
                        break;
                    case 'UPDATE':
                        \DB::table('permissions')->where('name', $name)->update($change['data']);
                        break;
                    default:
                        \DB::table('permissions')->where('name', $name)->delete();
                        break;
                }
            }
            return count($changes);
        });
    }
}
