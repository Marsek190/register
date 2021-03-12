<?php

namespace Common\Service\Transactional;

use Exception;
use Phalcon\Db\Adapter;
use Common\Service\Transactional\Exception\TransactionException;

class TransactionManager implements TransactionManagerInterface
{
    private Adapter $adapter;
  
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /** @inheritDoc */
    public function transactional(callable $callback)
    {
        if (!$this->adapter->isUnderTransaction()) {
            $this->adapter->begin();
            try {
                $result = call_user_func($callback);
                $this->adapter->commit();

                return $result;
            } catch (Exception $e) {
                if ($this->adapter->isUnderTransaction()) {
                    $this->adapter->rollback();
                }
                throw new TransactionException($e->getMessage(), $e->getCode(), $e);
            }
        } else {
            $savepointName = 'savepoint_' . uniqid();
            if (!$this->adapter->createSavepoint($savepointName)) {
                throw new TransactionException();
            }
            try {
                $result = call_user_func($callback);
                if (!$this->adapter->releaseSavepoint($savepointName)) {
                    throw new TransactionException();
                }

                return $result;
            } catch (Exception $e) {
                if ($this->adapter->isNestedTransactionsWithSavepoints()) {
                    $this->adapter->rollbackSavepoint($savepointName);
                }
                throw new TransactionException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }
}
