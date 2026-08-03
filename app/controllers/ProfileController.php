<?php
/**
 * ProfileController.php
 * Per handoff doc Section 6 (teknisi screen 5): "Profile -- name,
 * logout". The teknisi navbar has linked to `page=profile` since
 * Phase 2 (see navbar.php) but no route/controller/view backed it
 * until now -- it 404'd. Admin doesn't get a navbar slot for this
 * (Section 5: "Profile/Logout via top-corner icon, not a navbar
 * slot"), but the route itself is role-agnostic since there's nothing
 * teknisi-specific about showing your own account info.
 */

require_once __DIR__ . '/../core/Auth.php';

class ProfileController
{
    public function index(): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        $pageTitle = 'Profile';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/profile.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }
}
