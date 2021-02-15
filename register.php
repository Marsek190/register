

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
	private UserRegisterRepositoryInterface $userRegisterRepo,
	private GuardRepositoryInterface $guardRepo,
	private SessionContainerInterface $sessionContainer,
	private UserRegisterFactory $userRegisterFactory
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
	if ($errors = $this->validator->validate($userRegisterDto)) {
	    throw new BadUserRegisterDto($errors);
	}
	    
	$phone = new Phone($userRegisterDto->phone);
	$email = new Email($userRegisterDto->email);
	
	if (!$this->guardByContactInformation($phone, $email)) {
	    throw new UserAlreadyExistsError();
	}

	$userRegister = $this->userRegisterFactory->uploadFromStepOne($phone, $email, $userRegisterDto);
        $id = $this->userRegisterRepo->save($userRegister);
        $this->sessionContainer->add(self::class, $id);
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
	private UserRegisterRepositoryInterface $userRegisterRepo,
	private SessionContainerInterface $sessionContainer,
	private UserRegisterFactory $userRegisterFactory
    ) { }

    public function process(UserRegisterDto $userRegisterDto): void
    {
	if ($errors = $this->validator->validate($userRegisterDto)) {
	    throw new BadUserRegisterDto($errors);
	}

	$id = $this->sessionContainer->get(RegistrationStepFirst::class);
	$userRegisterTmp = $this->userRegisterRepo->findByIdOrFail($id);
	
	// ...
	$userRegister = $this->userRegisterFactory->uploadFromStepTwo($userRegisterDto, $userRegisterTmp);
	$this->userRegisterRepo->save($userRegister);
    }
}

final class RegistrationStepThird implements RegistrationStep
{
    public function __construct(
	private \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
	private UserRegisterRepositoryInterface $userRegisterRepo,
	private SessionContainerInterface $sessionContainer,
	private UserRegisterFactory $userRegisterFactory
    ) { }

    public function process(UserRegisterDto $userRegisterDto): void
    {
	if ($errors = $this->validator->validate($userRegisterDto)) {
	    throw new BadUserRegisterDto($errors);
	}

	$id = $this->sessionContainer->get(RegistrationStepFirst::class);
	$userRegisterTmp = $this->userRegisterRepo->findByIdOrFail($id);
	
	// ...
	$userRegister = $this->userRegisterFactory->uploadFromStepThree($userRegisterDto, $userRegisterTmp);
	$this->userRegisterRepo->save($userRegister);
	$this->sessionContainer->remove(RegistrationStepFirst::class);
    }
}

interface RequestInterface { }

class SorterRequest implements RequestInterface
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
    public function uploadFromStepOne(Phone $phone, Email $email, UserRegisterStepFirstDto $userRegisterDto): UserRegister
    {
	$userRegister = new UserRegister();
        $userRegister->id = $this->uuidGenerator->next();
        $userRegister->name = $userRegisterDto->name;
        $userRegister->phone = $phone;
        $userRegister->email = $email;
        $userRegister->step = new Step();
       
  	return $userRegister;
    }

    public function uploadFromStepTwo(UserRegisterStepSecondDto $userRegisterDto, UserRegister $userRegister): UserRegister
    {
	$userRegister->company = $userRegisterDto->company;
	$userRegister->step->next();
	// ...	

	return $userRegister;
    }    

    public function uploadFromStepThree(UserRegisterStepThirdDto $userRegisterDto, UserRegister $userRegister): UserRegister
    {
	// ...	
	$userRegister->verified = true;
	$userRegister->createdAt = $this->dateTimeFactory->current();
	$userRegister->step->next();

	return $userRegister;
    }
}

class UserRegisterIdFetcher
{
    public function __construct(
        private SessionInterface $session, 
	private PropertyBag $cookie, 
	private HasherInterface $hasher
    ) { }

    public function fetch(): ?int
    {
	if ($this->session->has($this->getName())) {
	    return $this->session->get($this->getName());
	}
	
	if ($this->cookie->has($this->getName())) {
	    return $this->hasher->ecncrypt($this->cookie->get($this->getName()));
	}
	
	return null;
    }
	
    public function getName(): string
    {
	return 'user_register_id';
    }
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

class Step
{
    private int $current = 1;
   
    private const MAX_STEP_COUNT = 3;

    public function current(): int
    {
	return $this->current;
    }

    public function next(): self
    {
	if ($this->current > self::MAX_STEP_COUNT) {
	    throw new \DomainException('');
	}
	
        $this->current++;
	
	return $this;
    }

}

class ContactInformationGuardDto
{
    public function __construct(
	public string $phone, 
	public string $email
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


class RequestDtoFactory
{
    private $stepToDtoMap;

    public function __construct(private SerializerInterface $serializer)
    { 
	$this->stepToDtoMap = $this->getStepToDtoMap();
    }

    public function create(RequestInterface $request): UserRegisterDto
    {
	$step = !is_null($request->post('step')) ? (int) $request->post('step') : null;
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
    public function convertToDBALEntity(UserRegister $userRegister): UserRegisterEntity;
    public function convertToModel(UserRegisterEntity $userRegister): UserRegister;
}

class UserRegisterConverter implements UserRegisterConverterInterface
{
    public function __construct(private \SharedKernel\Infrastructure\HydratorInterface $hydrator) { }

    public function convertToDBALEntity(UserRegister $userRegister): UserRegisterEntity
    {
	// ...
    }

    public function convertToModel(UserRegisterEntity $userRegister): UserRegister
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


class RegistrationController
{
    public function __construct(
	private RegistrationStepHandlerFactory $handlerFactory, 
	private RegistrationStepHandlerFactory $requestDtoFactory
    ) { }

    public function registerAction(RequestInterface $request)
    {
	$handler = $this->handlerFactory->create((int) $request->post('step'));
        $userRegisterDto = $this->requestDtoFactory->create($request);
	
	$handler->process($userRegisterDto);
    }
}
