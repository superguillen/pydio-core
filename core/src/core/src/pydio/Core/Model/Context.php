<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Model;

use Pydio\Core\Services\ConfService;

defined('AJXP_EXEC') or die('Access not allowed');


class Context implements ContextInterface
{

    /**
     * @var string
     */
    private $userId;

    /**
     * @var
     */
    private $userObject;

    /**
     * @var
     */
    private $repositoryId;

    /**
     * @var
     */
    private $repositoryObject;

    public function __construct($userId = null, $repositoryId = null)
    {
        if($userId !== null) {
            $this->userId = $userId;
        }
        if($repositoryId !== null){
            $this->repositoryId = $repositoryId;
        }
    }

    /**
     * @return boolean
     */
    public function hasUser()
    {
        return !empty($this->userId);
    }

    /**
     * @return UserInterface|null
     */
    public function getUser()
    {
        if(isSet($this->userObject)){
            return $this->userObject;
        }
        if(isSet($this->userId)){
            $this->userObject = ConfService::getConfStorageImpl()->createUserObject($this->userId);
            return $this->userObject;
        }
        return null;
    }

    /**
     * @param string $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @param UserInterface $user
     */
    public function setUserObject($user)
    {
        $this->userObject = $user;
        $this->userId = $user->getId();
    }

    public function resetUser(){
        $this->userId = null;
        $this->userObject = null;
    }
    
    /**
     * @return boolean
     */
    public function hasRepository()
    {
        return (!empty($this->repositoryId));
    }

    /**
     * @return RepositoryInterface|null
     */
    public function getRepository()
    {
        if(isSet($this->repositoryId)){
            if(!isSet($this->repositoryObject)){
                $this->repositoryObject = ConfService::getRepositoryById($this->repositoryId);
            }
            return $this->repositoryObject;
        }
        return null;
    }

    /**
     * @param string $repositoryId
     */
    public function setRepositoryId($repositoryId)
    {
        $this->repositoryId = $repositoryId;
    }

    /**
     * @param RepositoryInterface $repository
     */
    public function setRepositoryObject($repository)
    {
        $this->repositoryObject = $repository;
        $this->repositoryId = $repository->getId();
    }

    public function resetRepository()
    {
        $this->repositoryId = $this->repositoryObject = null;
    }
}