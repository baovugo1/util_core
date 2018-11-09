<?php

namespace go1\util\tests;

use go1\util\AccessChecker;
use go1\util\edge\EdgeTypes;
use go1\util\schema\mock\PortalMockTrait;
use go1\util\schema\mock\UserMockTrait;
use go1\util\Text;
use Symfony\Component\HttpFoundation\Request;

class AccessCheckerTest extends UtilCoreTestCase
{
    use PortalMockTrait;
    use UserMockTrait;

    public function testValidAccount()
    {
        $portalId = $this->createPortal($this->db, ['title' => 'qa.mygo1.com']);
        $userId = $this->createUser($this->db, ['instance' => 'accounts.gocatalyze.com']);
        $accountId = $this->createUser($this->db, ['instance' => 'qa.mygo1.com']);
        $this->link($this->db, EdgeTypes::HAS_ACCOUNT, $userId, $accountId);

        $req = new Request;
        $jwt = $this->jwtForUser($this->db, $userId, 'qa.mygo1.com');
        $payload = Text::jwtContent($jwt);
        $req->attributes->set('jwt.payload', $payload);

        $account = (new AccessChecker)->validAccount($req, 'qa.mygo1.com');
        $this->assertEquals($account->id, $accountId);

        $account = (new AccessChecker)->validAccount($req, $portalId);
        $this->assertEquals($account->id, $accountId);
    }

    public function testVirtualAccount()
    {
        $userId = $this->createUser($this->db, ['instance' => 'accounts.gocatalyze.com']);
        $accountId = $this->createUser($this->db, ['instance' => $portalName = 'portal.mygo1.com']);
        $this->link($this->db, EdgeTypes::HAS_ACCOUNT_VIRTUAL, $userId, $accountId);

        $payload = $this->getPayload([]);
        $req = new Request;
        $req->attributes->set('jwt.payload', $payload);

        $access = new AccessChecker();
        $account1 = $access->validUser($req, $portalName);
        $this->assertFalse($account1);

        $account2 = $access->validUser($req, $portalName, $this->db);
        $this->assertEquals($accountId, $account2->id);
    }

    public function testIsStudentManager()
    {
        $manager2Id = $this->createUser($this->db, ['mail' => $manager2Mail = 'manager2@mail.com', 'instance' => $accountsName = 'accounts.gocatalyze.com']);
        $managerId = $this->createUser($this->db, ['mail' => $managerMail = 'manager@mail.com', 'instance' => $accountsName = 'accounts.gocatalyze.com']);
        $studentId = $this->createUser($this->db, ['mail' => $studentMail = 'student@mail.com', 'instance' => $portalName = 'portal.mygo1.com']);
        $this->link($this->db, EdgeTypes::HAS_MANAGER, $studentId, $managerId);

        # Is manager
        $req = new Request;
        $req->attributes->set('jwt.payload', $this->getPayload(['id' => $managerId, 'mail' => $managerMail]));
        $this->assertTrue((new AccessChecker)->isStudentManager($this->db, $req, $studentMail, $portalName, EdgeTypes::HAS_MANAGER));

        # Is not manager
        $req = new Request;
        $req->attributes->set('jwt.payload', $this->getPayload(['id' => $manager2Id, 'mail' => $manager2Mail]));
        $this->assertFalse((new AccessChecker)->isStudentManager($this->db, $req, $studentMail, $portalName, EdgeTypes::HAS_MANAGER));
    }
}
