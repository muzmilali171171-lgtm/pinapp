<?php
/**
 * Team Management — a user (the "owner") can invite people by email to share their
 * account's resources (credits, BYOK AI keys under Settings → AI Api / Image Generation
 * Models). A team member signs in with their OWN login and never sees the owner's or any
 * other member's data (pins, websites, articles, etc. all stay scoped to each person's own
 * user_id as before) — only resource usage (credits + BYOK keys) is shared, resolved via
 * team_effective_owner_id() below.
 *
 * If the invited email isn't a registered user yet, the invite stays 'pending' and is
 * auto-activated the moment someone registers with that exact email (see auth/register.php).
 */

/** Resolves who a user's shared resources (credits, BYOK AI keys) should be drawn from:
 *  the user themself, unless they're an active member of someone else's team. Falls back
 *  to the user's own id (rather than throwing) if team_members isn't migrated yet — this
 *  is called from get_user_credits()/deduct_user_credits() on nearly every page, so it must
 *  never take the whole app down just because this one table is missing. */
function team_effective_owner_id(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare("SELECT owner_id FROM team_members WHERE member_user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        $ownerId = $stmt->fetchColumn();
        return $ownerId ? (int)$ownerId : $userId;
    } catch (Throwable $e) {
        return $userId;
    }
}

/** Members of the given user's OWN team (they are the owner/inviter). */
function get_owned_team_members(PDO $pdo, int $ownerId): array
{
    try {
        $stmt = $pdo->prepare("SELECT tm.*, u.name AS member_name, u.email AS member_email
            FROM team_members tm
            LEFT JOIN users u ON u.id = tm.member_user_id
            WHERE tm.owner_id = ? AND tm.status != 'removed'
            ORDER BY tm.invited_at DESC");
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Teams (belonging to other people) that the given user is an active member of. */
function get_teams_user_belongs_to(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("SELECT tm.*, u.name AS owner_name, u.email AS owner_email
            FROM team_members tm
            JOIN users u ON u.id = tm.owner_id
            WHERE tm.member_user_id = ? AND tm.status = 'active'
            ORDER BY tm.joined_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Invite by email. Auto-activates immediately if that email already has an account. */
function team_invite_member(PDO $pdo, int $ownerId, string $email): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }
    try {
        $ownerRow = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $ownerRow->execute([$ownerId]);
        if (strtolower((string)$ownerRow->fetchColumn()) === $email) {
            return ['ok' => false, 'error' => "That's your own email address."];
        }

        $existingUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $existingUser->execute([$email]);
        $memberUserId = $existingUser->fetchColumn() ?: null;

        // Someone can only be an active member of ONE team at a time (their resources come
        // from a single owner) — block re-inviting someone who already belongs elsewhere.
        if ($memberUserId) {
            $elsewhere = $pdo->prepare("SELECT owner_id FROM team_members WHERE member_user_id = ? AND status = 'active' AND owner_id != ?");
            $elsewhere->execute([$memberUserId, $ownerId]);
            if ($elsewhere->fetchColumn()) {
                return ['ok' => false, 'error' => 'This person is already on another team.'];
            }
        }

        $status = $memberUserId ? 'active' : 'pending';
        $stmt = $pdo->prepare("INSERT INTO team_members (owner_id, member_user_id, invited_email, status, joined_at)
            VALUES (?, ?, ?, ?, " . ($memberUserId ? 'NOW()' : 'NULL') . ")
            ON DUPLICATE KEY UPDATE status = IF(status = 'removed', VALUES(status), status), member_user_id = VALUES(member_user_id)");
        $stmt->execute([$ownerId, $memberUserId, $email, $status]);
        return ['ok' => true, 'error' => null, 'status' => $status];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not save this invite right now. Please try again later.'];
    }
}

function team_remove_member(PDO $pdo, int $ownerId, int $teamMemberRowId): void
{
    try {
        $pdo->prepare("UPDATE team_members SET status = 'removed' WHERE id = ? AND owner_id = ?")->execute([$teamMemberRowId, $ownerId]);
    } catch (Throwable $e) {
        // no-op
    }
}

function team_leave(PDO $pdo, int $userId, int $teamMemberRowId): void
{
    try {
        $pdo->prepare("UPDATE team_members SET status = 'removed' WHERE id = ? AND member_user_id = ?")->execute([$teamMemberRowId, $userId]);
    } catch (Throwable $e) {
        // no-op
    }
}

/** Call right after a new user registers — activates any pending invite(s) sent to their email. */
function team_activate_pending_invites(PDO $pdo, int $newUserId, string $email): void
{
    try {
        $pdo->prepare("UPDATE team_members SET member_user_id = ?, status = 'active', joined_at = NOW()
            WHERE invited_email = ? AND status = 'pending'")->execute([$newUserId, strtolower(trim($email))]);
    } catch (Throwable $e) {
        // Registration must still succeed even if team_members isn't migrated yet.
    }
}
