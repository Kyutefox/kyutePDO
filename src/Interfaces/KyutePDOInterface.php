<?php

namespace Kyutefox\KyutePDO\Interfaces;

interface KyutePDOInterface
{
    /**
     * @param null $type
     * @param null $argument
     * @return mixed
     */

    public function get($type = null, $argument = null);

    /**
     * @param null $type
     * @param null $argument
     * @return mixed
     */

    public function getAll($type = null, $argument = null);

    /**
     * @param array $data
     * @param false $type
     * @return mixed
     */

    public function update(array $data, $type = false);

    /**
     * @param array $data
     * @param false $type
     * @return mixed
     */

    public function insert(array $data, $type = false);

    /**
     * @param false $type
     * @return mixed
     */

    public function delete($type = false);
}
