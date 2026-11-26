<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\Audit;
use BBA\Core\Db;
use BBA\Models\Team;
use BBA\Models\User;

final class TeamController extends Controller
{
    public function index(): void
    {
        $account = $this->auth();
        $id = (int) $account['id'];

        $this->view('account/teams', [
            'title' => 'Teams',
            'account' => $account,
            'owned' => Team::forOwner($id),
            'related' => Team::forAccount($id),
            'allTeams' => Team::catalog(),
            'canCreate' => level_ok($account, 'team') && (int) ($account['points'] ?? 0) >= Team::COST,
            'cost' => Team::COST,
            'requiredLevel' => level_required('team'),
        ]);
    }

    public function store(): void
    {
        $this->csrf();
        $account = $this->auth();
        if (!level_ok($account, 'team')) {
            $this->fail(level_gate_error('team'));
            $this->redirect('/account/teams');
        }
        if ((int) ($account['points'] ?? 0) < Team::COST) {
            $this->fail('Creating a team costs ' . Team::COST . ' points.');
            $this->redirect('/account/teams');
        }

        $name = trim((string) $this->input('name'));
        $description = trim((string) $this->input('description'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $this->fail('Use a team name between 2 and 80 characters.');
            $this->redirect('/account/teams');
        }

        $team = Team::create((int) $account['id'], $name, $description);
        if ($team === null) {
            $this->fail('Could not create that team.');
            $this->redirect('/account/teams');
        }
        User::addPoints((int) $account['id'], -Team::COST);
        User::addXp((int) $account['id'], Team::XP);
        Audit::log('account.team_created', (int) $account['id'], null, 'team', (int) $team['id']);
        $this->succeed('Team created. The Teams showcase on your profile shows your teams.');
        $this->redirect('/account/teams?team=' . (int) $team['id']);
    }

    public function addMember(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $team = $this->manageableTeam($params, (int) $account['id']);

        $username = mb_strtolower(trim((string) $this->input('username')));
        $target = User::findByUsername($username);
        if ($target === null) {
            $this->fail('Unknown username.');
            $this->redirect('/account/teams?team=' . (int) $team['id']);
        }
        if (!Team::addMember((int) $team['id'], (int) $target['id'])) {
            $this->fail('That account is already on the team or cannot be added.');
            $this->redirect('/account/teams?team=' . (int) $team['id']);
        }
        Audit::log('account.team_member_added', (int) $account['id'], null, 'team', (int) $team['id'], ['member_id' => (int) $target['id']]);
        $this->succeed('Member added.');
        $this->redirect('/account/teams?team=' . (int) $team['id']);
    }

    public function setRole(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $team = $this->manageableTeam($params, (int) $account['id'], true);
        $memberId = (int) ($params['member'] ?? 0);
        $role = trim((string) $this->input('role'));
        if (!Team::setRole((int) $team['id'], $memberId, $role)) {
            $this->fail('That role could not be applied. The owner role cannot be changed.');
        } else {
            $this->succeed('Role updated.');
        }
        $this->redirect('/account/teams?team=' . (int) $team['id']);
    }

    public function removeMember(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $team = $this->manageableTeam($params, (int) $account['id'], true);
        if (!Team::removeMember((int) $team['id'], (int) ($params['member'] ?? 0))) {
            $this->fail('That member could not be removed.');
        } else {
            $this->succeed('Member removed.');
        }
        $this->redirect('/account/teams?team=' . (int) $team['id']);
    }

    public function subscribe(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $team = Team::find((int) ($params['id'] ?? 0));
        if ($team === null) {
            $this->fail('Team not found.');
            $this->redirect('/account/teams');
        }
        Team::subscribe((int) $team['id'], (int) $account['id']);
        $this->succeed('Subscribed to ' . $team['name'] . '. You will get its announcements and its discussion chat in bbchat.');
        $this->redirect('/account/teams?team=' . (int) $team['id']);
    }

    public function announce(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $team = $this->manageableTeam($params, (int) $account['id']);
        $teamId = (int) $team['id'];
        if (!$this->allowed('team_announce', (string) $teamId, 5, 900)) {
            $this->fail('This team has sent too many announcements recently. Try again later.');
            $this->redirect('/account/teams?team=' . $teamId);
        }

        $subject = mb_substr(trim((string) $this->input('subject')), 0, 120);
        $body = mb_substr(trim((string) $this->input('body')), 0, 4000);
        if ($subject === '' || $body === '') {
            $this->fail('Add a subject and a message.');
            $this->redirect('/account/teams?team=' . $teamId);
        }

        $announcementId = Team::announce($teamId, (int) $account['id'], $subject, $body);
        $recipients = Team::announcementRecipients($teamId, (int) $account['id']);
        foreach ($recipients as $recipientId) {
            Db::insert('notifications', [
                'account_id' => $recipientId,
                'actor_id' => (int) $account['id'],
                'subject' => mb_substr($team['name'] . ': ' . $subject, 0, 120),
                'body' => $body,
                'icon' => 'users',
                'link_text' => 'Open team',
                'link_url' => url('/account/teams?team=' . $teamId),
            ]);
        }
        Audit::log('account.team_announced', (int) $account['id'], null, 'team', $teamId, [
            'announcement_id' => $announcementId,
            'recipients' => count($recipients),
        ]);
        $this->succeed('Announcement sent to ' . count($recipients) . ' member(s) and subscriber(s).');
        $this->redirect('/account/teams?team=' . $teamId);
    }

    public function unsubscribe(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $team = Team::find((int) ($params['id'] ?? 0));
        if ($team !== null) {
            Team::unsubscribe((int) $team['id'], (int) $account['id']);
            $this->succeed('Unsubscribed from ' . $team['name'] . '.');
        }
        $this->redirect('/account/teams?team=' . (int) ($params['id'] ?? 0));
    }

    public function destroy(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $team = $this->manageableTeam($params, (int) $account['id'], true);
        Team::delete((int) $team['id']);
        Audit::log('account.team_deleted', (int) $account['id'], null, 'team', (int) $team['id']);
        $this->succeed('Team deleted. Its showcase was removed; other showcases were not touched.');
        $this->redirect('/account/teams');
    }

    private function manageableTeam(array $params, int $accountId, bool $ownerOnly = false): array
    {
        $team = Team::find((int) ($params['id'] ?? 0));
        if ($team === null) {
            $this->fail('Team not found.');
            $this->redirect('/account/teams');
        }
        $role = Team::roleOf((int) $team['id'], $accountId);
        $allowed = $ownerOnly ? $role === 'Owner' : in_array($role, ['Owner', 'Co-owner'], true);
        if (!$allowed) {
            $this->fail('You do not have permission to manage this team.');
            $this->redirect('/account/teams?team=' . (int) $team['id']);
        }

        return $team;
    }
}
