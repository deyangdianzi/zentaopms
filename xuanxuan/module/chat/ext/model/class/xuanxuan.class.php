<?php
class xuanxuanChat extends chatModel
{
    public function downloadXXD($setting, $type)
    {
        $data = new stdClass();
        $data->uploadFileSize = $setting->uploadFileSize;
        $data->isHttps        = $setting->isHttps;
        $data->sslcrt         = $setting->sslcrt;
        $data->sslkey         = $setting->sslkey;
        $data->ip             = $setting->ip;
        $data->chatPort       = $setting->chatPort;
        $data->commonPort     = $setting->commonPort;
        $data->maxOnlineUser  = isset($setting->maxOnlineUser) ? $setting->maxOnlineUser : 0;
        $data->key            = $this->config->xuanxuan->key;
        $data->os             = $setting->os;
        $data->version        = $this->config->xuanxuan->version;
        $data->downloadType   = $type;

        $server = $this->getServer();
        $data->server = $server;
        $data->host   = trim($server, '/') . getWebRoot();

        $url    = "https://www.chanzhi.org/license-downloadxxd-zentao.html";
        $result = common::http($url, $data);
        
        if($type == 'config')
        {
            $this->sendDownHeader('xxd.conf', 'conf', $result, strlen($result));
        }
        else
        {
            header("Location: $result");
        }

        $this->loadModel('setting')->setItem('system.common.xxserver.installed', 1);
        exit;
    }

    public function sendDownHeader($fileName, $fileType, $content, $fileSize = 0)
    {
        /* Set the downloading cookie, thus the export form page can use it to judge whether to close the window or not. */
        setcookie('downloading', 1, 0, '', '', false, true);

        /* Append the extension name auto. */
        $extension = '.' . $fileType;
        if(strpos($fileName, $extension) === false) $fileName .= $extension;

        /* urlencode the fileName for ie. */
        $isIE11 = (strpos($this->server->http_user_agent, 'Trident') !== false and strpos($this->server->http_user_agent, 'rv:11.0') !== false); 
        if(strpos($this->server->http_user_agent, 'MSIE') !== false or $isIE11) $fileName = urlencode($fileName);

        /* Judge the content type. */
        $mimes = $this->config->chat->mimes;
        $contentType = isset($mimes[$fileType]) ? $mimes[$fileType] : $mimes['default'];
        if(empty($fileSize) and $content) $fileSize = strlen($content);

        header("Content-type: $contentType");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-length: {$fileSize}");
        header("Pragma: no-cache");
        header("Expires: 0");
        die($content);
    }

    public function getExtensionList($userID)
    {
        $entries = array();
        $baseURL = $this->getServer();

        $this->loadModel('user');
        $user = $this->dao->select('*')->from(TABLE_USER)->where('id')->eq($userID)->fetch();
        $user->admin  = strpos($this->app->company->admins, ",{$user->account},") !== false;
        $user->rights = $this->user->authorize($user->account);
        $user->groups = $this->user->getGroups($user->account);
        $user->view   = $this->user->grantUserView($user->account, $user->rights['acls']);

        $this->session->set('user', $user);
        $this->app->user = $this->session->user;

        $products  = trim($this->app->user->view->products, ',');
        $projects  = trim($this->app->user->view->projects, ',');
        $products  = empty($products) ? array() : explode(',', $products);
        $projects  = empty($projects) ? array() : explode(',', $projects);
        $libIdList = array_keys($this->loadModel('doc')->getLibs('all'));
        $productID = isset($products[0])  ? $products[0]  : 1;
        $projectID = isset($projects[0])  ? $projects[0]  : 1;
        $libID     = isset($libIdList[0]) ? $libIdList[0] : 1;

        $actions = new stdclass();
        if(common::hasPriv('bug',   'create') and !empty($products)) $actions->createBug   = array('title' => $this->lang->chat->createBug,   'url' => $baseURL . str_replace('/xuanxuan.php', '/index.php', helper::createLink('bug', 'create', "product=$productID", 'xhtml')), 'height' => "600px", 'width' => "800px");
        if(common::hasPriv('doc',   'create') and !empty($libIdList))$actions->createDoc   = array('title' => $this->lang->chat->createDoc,   'url' => $baseURL . str_replace('/xuanxuan.php', '/index.php', helper::createLink('doc', 'create', "libID=$libID", 'xhtml')), 'height' => "600px", 'width' => "800px");
        if(common::hasPriv('story', 'create') and !empty($products)) $actions->createStory = array('title' => $this->lang->chat->createStory, 'url' => $baseURL . str_replace('/xuanxuan.php', '/index.php', helper::createLink('story', 'create', "product=$productID", 'xhtml')), 'height' => "600px", 'width' => "800px");
        if(common::hasPriv('task',  'create') and !empty($projects)) $actions->createTask  = array('title' => $this->lang->chat->createTask,  'url' => $baseURL . str_replace('/xuanxuan.php', '/index.php', helper::createLink('task', 'create', "project=$projectID", 'xhtml')), 'height' => "600px", 'width' => "800px");
        if(common::hasPriv('todo',  'create')) $actions->createTodo = array('title' => $this->lang->chat->createTodo,  'url' => $baseURL . str_replace('/xuanxuan.php', '/index.php', helper::createLink('todo', 'create', '', 'xhtml')), 'height' => "600px", 'width' => "800px");

        $urls = array();
        foreach($this->config->chat->cards as $moduleName => $methods)
        {
            foreach($methods as $methodName => $size)
            {
                if($this->config->requestType == 'GET')
                {
                    $url = $this->config->webRoot . "index.php?m={$moduleName}&f={$methodName}";
                }
                else
                {
                    $url = $this->config->webRoot . "{$moduleName}-{$methodName}-";
                }
                $urls[$url] = $size;
            }
        }

        $data = new stdClass();
        $data->entryID     = 1;
        $data->name        = 'zentao-integrated';
        $data->displayName = $this->lang->chat->zentaoIntegrate;
        $data->webViewUrl  = trim($baseURL . $this->config->webRoot, '/');
        $data->download    = $baseURL . $this->config->webRoot . 'data/xuanxuan/zentao-integrated.zip';
        $data->md5         = md5_file($this->app->getDataRoot() . 'xuanxuan/zentao-integrated.zip');

        $data->data['actions']  = $actions;
        $data->data['urls']     = $urls;
        $data->data['entryUrl'] = trim($baseURL . $this->config->webRoot, '/');

        $entries[] = $data;
        unset($_SESSION['user']);
        return $entries;
    }

    public function getServer()
    {
        $this->app->loadConfig('mail');
        $server = empty($this->config->mail->domain) ? commonModel::getSysURL() : $this->config->mail->domain;
        if(!empty($this->config->xuanxuan->server)) $server = $this->config->xuanxuan->server;

        return $server;
    }
}
