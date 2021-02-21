

user_register:
 - id: UNSIGNED INT (11) NOT NULL
 - name: VARCHAR (50) NOT NULL
 - phone: VARCHAR (25) NOT NULL
 - email: VARCHAR (25) NOT NULL
 - company: VARCHAR (255)
// ...

<?php

interface UserRegisterDto { }

class UserRegisterStepFirstDto implements UserRegisterDto
{
    public $name;
    public $phone;
    public $email;
}

class UserRegisterStepSecondDto implements UserRegisterDto
{
    /**
     * @Assert\Choice(
     *     choices = {"fiction", "non-fiction"},
     *     message = "Choose a valid genre."
     * )
     */
    public $company;
    // ...
}

interface RegistrationStep
{
    /**
     * @param UserRegisterDto $userDto
     * @return void
     * @throws BadUserRegisterDto
     */
    public function process(UserRegisterDto $userDto): void;
}

final class RegistrationStepFirst implements RegistrationStep
{
    public function __construct(
	private \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
	private GuardRepositoryInterface $guardRepo
    ) { }

    /**
     * @param UserRegisterDto|UserRegisterStepFirstDto $userRegisterDto
     * @return void
     * @throws BadUserRegisterDto
     * @throws UserAlreadyExistsError
     */
    public function process(UserRegisterDto $userRegisterDto): void
    {
	// ...
	Assert::isInstanceOf($userRegisterDto, UserRegisterStepFirstDto::class);

	if ($errors = $this->validator->validate($userRegisterDto)) {
	    throw new BadUserRegisterDto($errors);
	}
	    
	$phone = new Phone($userRegisterDto->phone);
	$email = new Email($userRegisterDto->email);
	
	if (!$this->guardByContactInformation($phone, $email)) {
	    throw new UserAlreadyExistsError();
	}
    }

    // ...

    private function guardByContactInformation(Phone $phone, Email $email): bool
    {
	$guardDto = new ContactInformationGuardDto($phone, $email);

	return !$this->guardRepo->checkByContactInformationGuardDto($guardDto);
    }
}

final class RegistrationStepSecond implements RegistrationStep
{
    public function __construct(
	private \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
	private GuardRepositoryInterface $guardRepo
    ) { }

    public function process(UserRegisterDto $userRegisterDto): void
    {
	if ($errors = $this->validator->validate($userRegisterDto)) {
	    throw new BadUserRegisterDto($errors);
	}
	
	// ...
    }
}

final class RegistrationStepThird implements RegistrationStep
{
    public function __construct(
	private \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
	private UserRegisterRepositoryInterface $userRegisterRepo,
	private SessionInterface $session,
	private UserRegisterFactory $userRegisterFactory,
	private UserRegisterConverter $userRegisterConverter
    ) { }

    public function process(UserRegisterDto $userRegisterDto): void
    {
	if ($errors = $this->validator->validate($userRegisterDto)) {
	    throw new BadUserRegisterDto($errors);
	}
	
	// ...
	    
	// конвертируем дто в доменную модель
	$userRegister = $this->userRegisterFactory->create($userRegisterDto);
	// конвертируем модель в сущность
	$userRegisterEntity = $this->userRegisterConverter->convertToEntity($userRegister);
	$id = $this->userRegisterRepo->save($userRegisterEntity);
	$this->session->set(UserRegister::class . '_id', $id);
    }
}

class SorterRequest
{
    /** @var string|null */
    private ?string $orderBy = null;

    /** @var bool|null */
    private ?string $asc = null;

    private array $orderByAllowed = ['price', 'new', 'popularity'];
    private array $ascAllowed = ['up', 'down'];

    /**
     * @param string $query
     */
    public function __construct(string $query)
    {
        if (empty($query)) {
            return;
        }

        parse_str($query, $reslt);

        if (
            !empty($reslt['order']) &&
            strpos($reslt['order'], '_') !== false
        ) {
            $parts = explode('_', $reslt['order'], 2) ?: [];

            unset($reslt);

            if (count($parts) !== 2) {
                return;
            }

            list($orderBy, $asc) = $parts;

            $this->setOrderBy($orderBy);
            $this->setAsc($asc);
        }
    }

    /** @return bool */
    public function orderByPrice(): bool
    {
        return $this->orderBy === 'price';
    }

    /** @return bool */
    public function orderByNewest(): bool
    {
        return $this->orderBy === 'new';
    }

    /** @return bool */
    public function orderByPopularity(): bool
    {
        return $this->orderBy === 'popularity';
    }

    /** @return bool|null */
    public function asc(): ?bool
    {
        return $this->asc;
    }

    /** @param string $orderBy */
    private function setOrderBy(string $orderBy): void
    {
        if (in_array($orderBy, $this->orderByAllowed)) {
            $this->orderBy = $orderBy;
        }
    }

    /** @param string $asc */
    private function setAsc(string $asc): void
    {
        if (in_array($asc, $this->ascAllowed)) {
            $this->asc = $asc === 'up';
        }
    }
}

class UserRegisterFactory
{
    public function create(UserRegisterStepFirstDto $userRegisterDto): UserRegister
    {
	$contactData = new ContactData(
	    new Email($userRegisterDto->name),
	    new Phone($userRegisterDto->phone),
	    // ...
	);
	$fullName = new FullName(
	    $userRegisterDto->firstName,
	    $userRegisterDto->secondName
	);
	$personalData = new PersonalData(
	    $fullName,
	    $userRegisterDto->taxpayerNumber,
	    $userRegisterDto->socialNumber,
	    // ...
	);
	$location = new Location(
	    $userRegisterDto->cityId,
	    $userRegisterDto->street,
	    $userRegisterDto->house,
	    $userRegisterDto->flat
	);
	$companyInfo = new CompanyInfo(
	    // ...
	);

	// ...    
	    
	return new UserRegister(
	    Id::next(), 
	    $contactData,
	    $personalData,
	    $location,
            $companyInfo,
	    // ...
	);
    }
	
    // or
    // public function getContanctData(UserRegisterStepFirstDto $userRegisterDto) { }
    // public function getPersonalData(UserRegisterStepFirstDto $userRegisterDto) { }
    // public function getLocation(UserRegisterStepFirstDto $userRegisterDto) { }
    // public function getCompanyInfo(UserRegisterStepFirstDto $userRegisterDto) { }
}

class CurrentTimeInteractor
{
    public function execute(): \DateTime
    {
        return new \DateTime();
    }
}

class Id
{
    private string $id;
	
    public function __construct(string $id)
    {
	$this->id = $id;
    }

    public function value(): string
    {
	return $this->id;
    }

    public static function next(): self
    {
	return new self((string) Uuid::uuid4());
    }

    public function __toString(): string
    {
	return $this->id;
    }
}

class ContactInformationGuardDto
{
    public function __construct(
	public Phone $phone, 
	public Email $email
    ) { }
}


// POPO or value object...
class Phone
{
    private string $phone;

    public function __construct(string $phone)
    {
	$this->phone = preg_replace('/+7|-|\s+/', '', $phone);
    }

    public function value(): string
    {
	return $this->phone;
    }

    public function format(): string
    {
	return sprintf(
	    '+7-%s-%s-%s-%s',
	    substr($this->phone, 0, 3),
	    substr($this->phone, 3, 3),
	    substr($this->phone, 6, 2),
	    substr($this->phone, 8, 2)
	);
    }

    public function __toString(): string
    {
	return $this->phone;
    }    
}

class Email
{
    private string $email;	

    public function __construct(string $email)
    {
	$this->email = mb_convert_case($email, MB_CASE_LOWER, mb_detect_encoding($email));
    }

    public function value(): string
    {
	return $this->email;
    }
}

class RequestDtoFactory
{
    private $stepToDtoMap;

    public function __construct(private SerializerInterface $serializer)
    { 
	$this->stepToDtoMap = $this->getStepToDtoMap();
    }

    public function create(RequestInterface $request, int $step): UserRegisterDto
    {
	if (!isset($this->stepToDtoMap[$step])) {
	    // ...
	}
	
	$dtoClass = $this->stepToDtoMap[$step];
	
	return $this->serializer->deserialize($request->post(), $dtoClass);
    }
	
    /**
     * @return array<int, string>
     */
    private function getStepToDtoMap(): array
    {
	return [
	    1 => UserRegisterStepFirstDto::class,
	    2 => UserRegisterStepSecondDto::class,
	    3 => UserRegisterStepThirdDto::class,
	];
    }
}

interface UserRegisterConverterInterface
{
    public function convertToEntity(UserRegister $model): UserRegisterEntity;
    public function convertToModel(UserRegisterEntity $entity): UserRegister;
}

class UserRegisterConverter implements UserRegisterConverterInterface
{
    public function convertToEntity(UserRegister $model): UserRegisterEntity
    {
	// ...
    }

    public function convertToModel(UserRegisterEntity $entity): UserRegister
    {
	// ...	
    }
}

class RegistrationStepHandlerFactory
{
    private $stepToHandlerMap;

    public function __construct(private Container $container)
    { 
	$this->stepToHandlerMap = $this->getStepToHandlerMap();
    } 

    public function create(int $step): RegistrationStep
    {
	if (!isset($this->stepToHandlerMap[$step])) {
	    // ...
	}
	
	$handler = $this->stepToDtoMap[$step];

	if (!$this->container->has($handler)) {
	    // ...
	}
	
	return $this->container->get($handler);
    }

    /**
     * @return array<int, string>
     */
    private function getStepToHandlerMap(): array
    {
	return [
	    1 => RegistrationStepFirst::class,
	    2 => RegistrationStepSecond::class,
	    3 => RegistrationStepThird::class,
	];
    }
}

class ResponseFactory
{
    // ...
    public function createSuccess(): ResponseInterface
    {
	return new Response('', 200);
    }

    public function createFail(): ResponseInterface
    {
	// отдаем 501 статус без вывода ошибки
    	return new Response('', 501);
    }

    public function createFromException(\Exception $e): ResponseInterface
    {
	switch (get_class($e)) {
	    case BadUserRegisterDto::class:
		return new Response(array_values($e->getErrors()), 401);
	    case UserAlreadyExistsError::class:
 		return new Response(['Пользователь с таким телефоном или почтой уже существует.'], 401);
	    // ...
	}

	return $this->createFail();
    }
}

class RegistrationController
{
    public function __construct(
	private RegistrationStepHandlerFactory $handlerFactory,
	private RequestDtoFactory $requestDtoFactory,
	private ResponseFactory $responseFactory,
        private LoggerInterface $logger
    ) { }

    public function registerAction(RequestInterface $request): ResponseInterface
    {
	if (is_null($request->post('step')) {
	    // ...
	}
	
	try {
	    $step = (int) $request->post('step');
	    $handler = $this->handlerFactory->create($step);
            $userRegisterDto = $this->requestDtoFactory->create($request, $step);
		
	    $handler->process($userRegisterDto);

            return $this->responseFactory->createSuccess();
	} catch (\Exception $e) {
	    if (!($e instanceof UserRegistrationError)) {
		// логируем серверную ошибку
	        $this->logger->error($this->getTags(), $e->getMessage());
	    }

    	    return $this->responseFactory->createFromException($e);
	}

    }

    private function getTags(): array
    {
    	return [
	    'user_registration',
	    'site',
 	    // ...
	];
    }
}
