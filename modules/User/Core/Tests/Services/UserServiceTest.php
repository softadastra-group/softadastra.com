<?php

namespace Modules\User\Core\Tests\Services;

use Ivi\Core\Utils\FlashMessage;
use PHPUnit\Framework\TestCase;
use Modules\User\Core\Services\UserService;
use Modules\User\Core\Repositories\UserRepository;
use Modules\User\Core\ValueObjects\Role;
use Ivi\Http\JsonResponse;
use Ivi\Http\RedirectResponse;
use Modules\User\Core\Factories\UserFactory;
use Modules\User\Core\Helpers\UserHelper;
use Modules\User\Core\Models\User;
use Modules\User\Core\Validator\UserValidator;
use Modules\User\Core\ValueObjects\Email;

final class UserServiceTest extends TestCase
{
    private UserService $service;
    /** @var UserRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryMock = $this->createMock(UserRepository::class);
        $this->service = new UserService($this->repositoryMock);
    }

    public function testRegisterSuccess(): void
    {
        $fullname = 'Gaspard Kirira';
        $email = 'gaspard@example.com';
        $password = 'StrongPass123!';
        $phone = '+256712345678';

        // --- 1) Email disponible
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        // --- 2) Save simulateur + ID utilisateur et rôles
        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function ($user) {
                $reflectionUser = new \ReflectionClass($user);
                $propertyUserId = $reflectionUser->getProperty('id');
                $propertyUserId->setAccessible(true);
                $propertyUserId->setValue($user, 1);

                foreach ($user->getRoles() as $role) {
                    $reflectionRole = new \ReflectionClass($role);
                    $propertyRoleId = $reflectionRole->getProperty('id');
                    $propertyRoleId->setAccessible(true);
                    $propertyRoleId->setValue($role, 1);
                }

                return $user;
            });

        // --- 3) Préparer la capture de JsonResponse
        $response = null;

        // --- 4) Mock UserValidator pour forcer succès
        $validatorMock = $this->getMockBuilder(UserValidator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate'])
            ->getMock();
        $validatorMock->method('validate')->willReturn([]);

        // --- 5) Créer un mock du service pour moquer issueAuthForUser
        $serviceMock = $this->getMockBuilder(UserService::class)
            ->setConstructorArgs([$this->repositoryMock])
            ->onlyMethods(['issueAuthForUser'])
            ->getMock();

        // Retour simulé du token
        $serviceMock->method('issueAuthForUser')->willReturn('fake-jwt-token');

        // Injecter le validator mocké
        $serviceMock->setValidator($validatorMock);

        // **Injection du handler JSON pour capturer la réponse**
        $serviceMock->setJsonResponseHandler(function (?JsonResponse $resp) use (&$response) {
            $response = $resp;
        });

        // --- 6) Appel de register
        $serviceMock->register($fullname, $email, $password, $phone);

        // --- 7) Vérification de la réponse
        $this->assertNotNull($response, 'Une réponse JSON doit être générée');

        /** @var JsonResponse $responseObj */
        $responseObj = $response;
        $data = $responseObj->getData();

        $this->assertEquals(201, $responseObj->status());
        $this->assertArrayHasKey('token', $data);
        $this->assertEquals('Account created successfully.', $data['message']);
    }

    public function testLoginWithCredentialsSuccess(): void
    {
        $email = 'gaspard@example.com';
        $password = 'StrongPass123!';

        // --- 1) Créer un mock User
        $role = new Role(1, 'user');

        $userMock = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPassword', 'getId', 'getEmail', 'getUsername', 'getRoles'])
            ->getMock();

        $userMock->method('getPassword')->willReturn(UserHelper::hashPassword($password));
        $userMock->method('getId')->willReturn(1);
        $userMock->method('getEmail')->willReturn(new Email($email)); // retourne un ValueObject Email
        $userMock->method('getUsername')->willReturn('gaspard');
        $userMock->method('getRoles')->willReturn([$role]);

        // --- 2) Mock repository
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($userMock);

        $this->repositoryMock->method('resetFailedAttempts')->with($email);
        $this->repositoryMock->method('incrementFailedAttempts')->with($email);
        $this->repositoryMock->method('acquireLock')->willReturn(true);
        $this->repositoryMock->method('releaseLock')->willReturn(true);

        // --- 3) Mock du service pour issueAuthForUser
        $serviceMock = $this->getMockBuilder(UserService::class)
            ->setConstructorArgs([$this->repositoryMock])
            ->onlyMethods(['issueAuthForUser'])
            ->getMock();

        $serviceMock->method('issueAuthForUser')->willReturn('fake-jwt-token');

        // --- 4) Capturer la réponse JSON
        $response = null;
        $serviceMock->setJsonResponseHandler(function (?JsonResponse $resp) use (&$response) {
            $response = $resp;
        });

        // --- 5) Appel de login
        $serviceMock->loginWithCredentials($email, $password);

        // --- 6) Vérification de la réponse
        $this->assertNotNull($response, 'Une réponse JSON doit être générée');

        /** @var JsonResponse $responseObj */
        $responseObj = $response;
        $data = $responseObj->getData();

        $this->assertEquals(200, $responseObj->status());
        $this->assertArrayHasKey('token', $data);
        $this->assertEquals('fake-jwt-token', $data['token']);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($email, (string)$data['user']['email']); // conversion Email -> string
    }

    public function testLoginWithGoogleOAuth_NewUser(): void
    {
        // 1️⃣ Préparer un googleUser simulé
        $googleUser = (object)[
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'picture' => 'avatar.jpg',
            'verifiedEmail' => true,
        ];

        // 2️⃣ Mock repository
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with(strtolower($googleUser->email))
            ->willReturn(null);

        $this->repositoryMock
            ->expects($this->once())
            ->method('createWithRoles')
            ->with(
                $this->callback(fn($data) => $data['email'] === strtolower($googleUser->email)
                    && $data['fullname'] === $googleUser->name),
                $this->isType('array')
            )
            ->willReturn(
                UserFactory::createFromArray([
                    'fullname' => $googleUser->name,
                    'email' => strtolower($googleUser->email),
                    'roles' => [new Role(1, 'user')],
                    'status' => 'active',
                    'verifiedEmail' => true,
                    'coverPhoto' => 'cover.jpg'
                ])
            );

        // 3️⃣ Mock issueAuthForUser
        $serviceMock = $this->getMockBuilder(UserService::class)
            ->setConstructorArgs([$this->repositoryMock])
            ->onlyMethods(['issueAuthForUser'])
            ->getMock();

        $serviceMock->method('issueAuthForUser')->willReturn('fake-jwt-token');

        // 4️⃣ Capturer les flash messages et les redirections
        $flashMessages = [];
        FlashMessage::setHandler(function ($type, $msg) use (&$flashMessages) {
            if (!isset($flashMessages[$type]) || !is_array($flashMessages[$type])) {
                $flashMessages[$type] = [];
            }
            $flashMessages[$type][] = $msg;
        });

        // Initialise comme string vide pour éviter le warning Intelephense
        $redirectUrl = '';
        RedirectResponse::setHandler(function ($url) use (&$redirectUrl) {
            $redirectUrl = $url;
        });

        // 5️⃣ Appel de la méthode
        $serviceMock->loginWithGoogleOAuth($googleUser);

        // 6️⃣ Assertions flash
        $this->assertArrayHasKey('success', $flashMessages, 'Un message de succès doit être ajouté');
        $this->assertNotEmpty($flashMessages['success'][0], 'Le message de succès ne doit pas être vide');
        $this->assertStringContainsString('Welcome', $flashMessages['success'][0]);

        // 7️⃣ Assertions redirection
        $this->assertNotEmpty($redirectUrl, 'Une redirection doit être déclenchée');
        $this->assertStringContainsString('/finalize-registration', $redirectUrl);
    }


    public function testRegisterEmailAlreadyTaken(): void
    {
        $fullname = 'Gaspard Kirira';
        $email = 'gaspard@example.com';
        $password = 'StrongPass123!';
        $phone = '+256712345678';

        // Crée un rôle avec ID
        $role = new Role(1, 'user');

        // Crée un utilisateur existant avec ce rôle
        $existingUser = UserFactory::createFromArray([
            'fullname' => $fullname,
            'email' => $email,
            'password' => $password,
            'roles' => [$role],
            'status' => 'active',
            'verifiedEmail' => true,
            'coverPhoto' => null,
            'bio' => null,
            'phone' => $phone,
        ]);

        // ID simulé pour l'utilisateur
        $reflectionUser = new \ReflectionClass($existingUser);
        $propertyUserId = $reflectionUser->getProperty('id');
        $propertyUserId->setAccessible(true);
        $propertyUserId->setValue($existingUser, 1);

        $this->repositoryMock
            ->method('findByEmail')
            ->with($email)
            ->willReturn($existingUser);

        /** @var JsonResponse|null $response */
        $response = null;

        JsonResponse::overrideSend(function (?JsonResponse $resp) use (&$response): void {
            $response = $resp;
        });

        $this->service->register($fullname, $email, $password, $phone);

        // S'assurer que $response est bien un objet JsonResponse
        $this->assertNotNull($response, 'Une réponse JSON doit être générée');

        /** @var JsonResponse $responseObj */
        $responseObj = $response;
        $data = $responseObj->getData();

        $this->assertEquals(409, $responseObj->status());
        $this->assertEquals('This email is already taken.', $data['error']);
    }
}
