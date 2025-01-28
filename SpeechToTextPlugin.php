<?php

use Slim\Interfaces\RouteCollectorProxyInterface;
use SpeechToTextPlugin\Routing\Router;
use SpeechToTextPlugin\Models\Job;

/**
 * @SuppressWarnings(PSR1.Classes.ClassDeclaration.MissingNamespace)
 * @SuppressWarnings(StaticAccess)
 */
class SpeechToTextPlugin extends StudIPPlugin implements SystemPlugin, PrivacyPlugin
{
    use TranslatablePluginTrait;

    private Router $router;

    public static function onEnable($pluginId)
    {
        RolePersistence::assignPluginRoles($pluginId, [7]);
    }

    public function __construct()
    {
        parent::__construct();

        require_once __DIR__ . '/vendor/autoload.php';

        NotificationCenter::on('UserDidDelete', $this->onDeleteUser(...));
        NotificationCenter::on('UserDataDidRemove', $this->onRemoveData(...));

        $this->initializeTranslation('speech-to-text');
        $this->router = new Router($this);

        if (UpdateInformation::isCollecting()) {
            $this->setUpdateInformation();
        } else {
            $this->addContentsNavigation();
        }
    }

    /**
     * @SuppressWarnings(UnusedFormalParameter)
     */
    public function registerSlimRoutes(RouteCollectorProxyInterface $app, string $unconsumedPath): void
    {
        $this->router->registerRoutes($app, $unconsumedPath);
    }

    private function addContentsNavigation(): void
    {
        if (Navigation::hasItem('/contents')) {
            Navigation::addItem('/contents/speech-to-text', $this->createNavigation());
        }
    }

    private function createNavigation(?string $cid = null): Navigation
    {
        $params = $cid ? ['cid' => $cid] : [];
        $navigation = new Navigation($this->_('Transkriptionen'));
        $navigation->setDescription('Automatisch Sprache in Text verwandeln');
        $navigation->setImage(Icon::create('wizard', 'navigation'));
        $navigation->setURL(PluginEngine::getURL($this, $params, '', true));

        // subnavigation
        $navigation->addSubnavigation('index', clone $navigation);

        return $navigation;
    }

    private function setUpdateInformation(): void
    {
        if (UpdateInformation::hasData(self::class)) {
            $since = new DateTime(UpdateInformation::getData(self::class)['since']);
            if ($since) {
                $latest = Job::latest(User::findCurrent());
                if ($latest && new DateTime('@' . $latest->chdate) > $since) {
                    UpdateInformation::setInformation(self::class, 'reload');
                }
            }
        }
    }

    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $storage object to store data into
     */
    public function exportUserData(StoredUserData $storage)
    {
        $db = \DBManager::get();

        $tableData = $db->fetchAll('SELECT * FROM speech_to_text_jobs WHERE user_id = ?', [$storage->user_id]);
        $storage->addTabularData('Audiotranskriptionen', 'speech_to_text_jobs', $tableData);

        $folderIds = $db->fetchFirst('SELECT id FROM folders WHERE user_id = ? AND range_type = ?', [$storage->user_id, \SpeechToTextPlugin\Models\Job::class]);
        $fileRefIds = $db->fetchFirst('SELECT id FROM file_refs WHERE folder_id IN (?)', [$folderIds]);

        foreach (\FileRef::findMany($fileRefIds) as $fileRef) {
            $storage->addFileRef($fileRef);
        }
    }

    private function onDeleteUser($event, $user)
    {
        $this->deleteUserData($user->id);
    }

    private function onRemoveData($event, $userId, $type)
    {
        $this->deleteUserData($userId);

        $info = match ($type) {
            'course_documents', 'personal_documents' => 'Dokumente von Nutzer X aus MyPlugin gelöscht',
            'course_contents', 'personal_contents' => 'Inhalte von Nutzer X aus MyPlugin gelöscht',
            'names' => 'Namen von Nutzer X aus MyPlugin gelöscht',
            default => null,
        };
        if ($info) {
            \PageLayout::postInfo($info);
        }
    }

    private function deleteUserData(string $userId): void
    {
        $db = \DBManager::get();

        $folderIds = $db->fetchFirst('SELECT id FROM folders WHERE user_id = ? AND range_type = ?', [$userId, \SpeechToTextPlugin\Models\Job::class]);
        $fileRefIds = $db->fetchFirst('SELECT id FROM file_refs WHERE folder_id IN (?)', [$folderIds]);
        $fileIds = $db->fetchFirst('SELECT file_id FROM file_refs WHERE folder_id IN (?)', [$folderIds]);

        $db->execute('DELETE FROM files WHERE id IN (?)', [$fileIds]);
        $db->execute('DELETE FROM file_refs WHERE id IN (?)', [$fileRefIds]);
        $db->execute('DELETE FROM folders WHERE id IN (?)', [$folderIds]);

        $db->execute('DELETE FROM speech_to_text_jobs WHERE user_id = ?', [$userId]);
        $db->execute('DELETE FROM speech_to_text_user_uploads WHERE user_id = ?', [$userId]);
    }
}
