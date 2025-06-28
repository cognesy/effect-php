<?php

declare(strict_types=1);

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Core\Either;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Option;
use EffectPHP\Core\Scope;

// Test services for integration
interface UserRepository
{
    public function findUser(int $id): Effect;
    public function saveUser(array $user): Effect;
}

interface EmailService  
{
    public function sendEmail(string $to, string $subject, string $body): Effect;
}

interface Logger
{
    public function info(string $message): Effect;
    public function error(string $message): Effect;
}

class InMemoryUserRepository implements UserRepository
{
    private array $users = [
        1 => ['id' => 1, 'email' => 'alice@example.com', 'name' => 'Alice'],
        2 => ['id' => 2, 'email' => 'bob@example.com', 'name' => 'Bob'],
    ];
    
    public function findUser(int $id): Effect
    {
        return Eff::sync(function() use ($id) {
            if (!isset($this->users[$id])) {
                throw new \RuntimeException("User $id not found");
            }
            return $this->users[$id];
        });
    }
    
    public function saveUser(array $user): Effect
    {
        return Eff::sync(function() use ($user) {
            $this->users[$user['id']] = $user;
            return $user['id'];
        });
    }
}

class MockEmailService implements EmailService
{
    public array $sentEmails = [];
    
    public function sendEmail(string $to, string $subject, string $body): Effect
    {
        return Eff::sync(function() use ($to, $subject, $body) {
            $this->sentEmails[] = compact('to', 'subject', 'body');
            return "email-sent-to-$to";
        });
    }
}

class TestLogger implements Logger
{
    public array $logs = [];
    
    public function info(string $message): Effect
    {
        return Eff::sync(function() use ($message) {
            $this->logs[] = ['level' => 'info', 'message' => $message];
        });
    }
    
    public function error(string $message): Effect
    {
        return Eff::sync(function() use ($message) {
            $this->logs[] = ['level' => 'error', 'message' => $message];
        });
    }
}

describe('Integration Tests', function () {
    
    describe('complete business workflow', function () {
        it('processes user registration with all services', function () {
            // Setup services
            $userRepo = new InMemoryUserRepository();
            $emailService = new MockEmailService();
            $logger = new TestLogger();
            
            // Create application layer
            $appLayer = Layer::fromValue($userRepo, UserRepository::class)
                ->combineWith(Layer::fromValue($emailService, EmailService::class))
                ->combineWith(Layer::fromValue($logger, Logger::class));
            
            // Business logic
            $registerUser = function(array $userData) {
                return Eff::service(Logger::class)
                    ->flatMap(fn($log) => $log->info("Starting user registration for {$userData['email']}"))
                    ->flatMap(fn() => Eff::service(UserRepository::class))
                    ->flatMap(fn($repo) => $repo->saveUser($userData))
                    ->flatMap(fn($userId) => Eff::service(EmailService::class))
                    ->flatMap(fn($email) => $email->sendEmail(
                        $userData['email'], 
                        'Welcome!', 
                        "Welcome {$userData['name']}!"
                    ))
                    ->flatMap(fn($emailId) => Eff::service(Logger::class))
                    ->flatMap(fn($log) => $log->info("Registration completed for {$userData['email']}"))
                    ->map(fn() => "User {$userData['name']} registered successfully");
            };
            
            // Execute workflow
            $userData = ['id' => 3, 'email' => 'charlie@example.com', 'name' => 'Charlie'];
            $workflow = $registerUser($userData);
            $result = Run::sync($appLayer->provideTo($workflow));
            
            expect($result)->toBe('User Charlie registered successfully');
            
            // Verify side effects
            expect($emailService->sentEmails)->toHaveCount(1)
                ->and($emailService->sentEmails[0]['to'])->toBe('charlie@example.com')
                ->and($logger->logs)->toHaveCount(2)
                ->and($logger->logs[0]['message'])->toContain('Starting user registration')
                ->and($logger->logs[1]['message'])->toContain('Registration completed');
        });
        
        it('handles failures gracefully with proper error handling', function () {
            $userRepo = new InMemoryUserRepository();
            $emailService = new MockEmailService();
            $logger = new TestLogger();
            
            $appLayer = Layer::fromValue($userRepo, UserRepository::class)
                ->combineWith(Layer::fromValue($emailService, EmailService::class))
                ->combineWith(Layer::fromValue($logger, Logger::class));
            
            $processUser = function(int $userId) {
                return Eff::service(UserRepository::class)
                    ->flatMap(fn($repo) => $repo->findUser($userId))
                    ->flatMap(fn($user) => 
                        Eff::service(EmailService::class)
                            ->flatMap(fn($email) => $email->sendEmail($user['email'], 'Update', 'Your profile was updated'))
                    )
                    ->map(fn() => 'Success')
                    ->catchError(\RuntimeException::class, fn($e) =>
                        Eff::service(Logger::class)
                            ->flatMap(fn($log) => $log->error("Failed to process user: {$e->getMessage()}"))
                            ->map(fn() => 'Error handled')
                    );
            };
            
            // Test with non-existent user
            $workflow = $processUser(999);
            $result = Run::sync($appLayer->provideTo($workflow));
            
            expect($result)->toBe('Error handled')
                ->and($logger->logs)->toHaveCount(1)
                ->and($logger->logs[0]['level'])->toBe('error')
                ->and($logger->logs[0]['message'])->toContain('User 999 not found');
        });
    });
    
    describe('resource management integration', function () {
        it('manages resources with automatic cleanup', function () {
            // Use object-based tracking to avoid PHP closure variable reference limitations
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $workflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('acquired');
                        return 'resource';
                    }),
                    release: fn($resource) => Eff::sync(function() use ($tracker) {
                        $tracker->track('released');
                    })
                )->flatMap(fn($resource) => Eff::sync(function() use ($resource, $tracker) {
                    $tracker->track('used');
                    return "Used: $resource";
                }));
            });
            
            $result = runEffect($workflow);
            
            expect($result)->toBe('Used: resource')
                ->and($tracker->events)->toBe(['acquired', 'used', 'released']);
        });
        
        it('ensures cleanup even on failure', function () {
            // Use object-based tracking to avoid PHP closure variable reference limitations
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $workflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('acquired');
                        return 'resource';
                    }),
                    release: fn($resource) => Eff::sync(function() use ($tracker) {
                        $tracker->track('released');
                    })
                )->flatMap(fn($resource) => Eff::sync(function() use ($tracker) {
                    $tracker->track('used');
                    throw new \RuntimeException('Failure during use');
                }));
            });
            
            $result = runEffectSafely($workflow);
            
            expect($result->isFailure())->toBeTrue()
                ->and($tracker->events)->toBe(['acquired', 'used', 'released']);
        });
    });
    
    describe('Option and Either integration', function () {
        it('chains Optional computations naturally', function () {
            $findUserName = fn($id) => $id === 1 
                ? Option::some('Alice') 
                : Option::none();
                
            $formatGreeting = fn($name) => Option::some("Hello, $name!");
            
            $workflow = $findUserName(1)
                ->flatMap($formatGreeting)
                ->toEffect(new \RuntimeException('User not found'));
            
            expect($workflow)->toProduceValue('Hello, Alice!');
            
            $failingWorkflow = $findUserName(999)
                ->flatMap($formatGreeting)
                ->toEffect(new \RuntimeException('User not found'));
            
            expect($failingWorkflow)->toFailWith(\RuntimeException::class);
        });
        
        it('handles Either-based error propagation', function () {
            $parseNumber = fn($str) => is_numeric($str)
                ? Either::right((int)$str)
                : Either::left("Invalid number: $str");
                
            $double = fn($n) => Either::right($n * 2);
            
            $workflow = $parseNumber('42')
                ->flatMap($double)
                ->toEffect();
            
            expect($workflow)->toProduceValue(84);
            
            $failingWorkflow = $parseNumber('invalid')
                ->flatMap($double)
                ->toEffect();
            
            expect($failingWorkflow)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('parallel processing integration', function () {
        it('processes multiple items concurrently', function () {
            $userRepo = new InMemoryUserRepository();
            $logger = new TestLogger();
            
            $appLayer = Layer::fromValue($userRepo, UserRepository::class)
                ->combineWith(Layer::fromValue($logger, Logger::class));
            
            $processUser = fn($id) => 
                Eff::service(UserRepository::class)
                    ->flatMap(fn($repo) => $repo->findUser($id))
                    ->map(fn($user) => $user['name']);
            
            $parallelProcessing = Eff::allInParallel([
                $processUser(1),
                $processUser(2)
            ])->flatMap(fn($names) =>
                Eff::service(Logger::class)
                    ->flatMap(fn($log) => $log->info("Processed users: " . implode(', ', $names)))
                    ->map(fn() => $names)
            );
            
            $workflow = Run::sync($appLayer->provideTo($parallelProcessing));
            
            expect($workflow)->toBe(['Alice', 'Bob'])
                ->and($logger->logs)->toHaveCount(1)
                ->and($logger->logs[0]['message'])->toBe('Processed users: Alice, Bob');
        });
    });
    
    describe('complex real-world scenario', function () {
        it('implements complete order processing workflow', function () {
            $logger = new TestLogger();
            $appLayer = Layer::fromValue($logger, Logger::class);
            
            $validateOrder = fn($order) => 
                empty($order['items']) 
                    ? Eff::fail(new \InvalidArgumentException('Order has no items'))
                    : Eff::succeed($order);
            
            $calculateTotal = fn($order) => Eff::sync(function() use ($order) {
                $total = array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $order['items']));
                return [...$order, 'total' => $total];
            });
            
            $processPayment = fn($order) => 
                $order['total'] > 1000
                    ? Eff::fail(new \RuntimeException('Payment failed: Amount too high'))
                    : Eff::succeed([...$order, 'paid' => true]);
            
            $sendConfirmation = fn($order) =>
                Eff::service(Logger::class)
                    ->flatMap(fn($log) => $log->info("Order confirmed: #{$order['id']} - {$order['total']}"))
                    ->map(fn() => $order);
            
            $processOrder = fn($order) =>
                Eff::service(Logger::class)
                    ->flatMap(fn($log) => $log->info("Processing order #{$order['id']}"))
                    ->flatMap(fn() => $validateOrder($order))
                    ->flatMap($calculateTotal)
                    ->flatMap($processPayment)
                    ->flatMap($sendConfirmation)
                    ->map(fn($processed) => "Order #{$processed['id']} completed successfully")
                    ->catchError(\InvalidArgumentException::class, fn($e) =>
                        Eff::service(Logger::class)
                            ->flatMap(fn($log) => $log->error("Validation error: {$e->getMessage()}"))
                            ->map(fn() => "Order validation failed")
                    )
                    ->catchError(\RuntimeException::class, fn($e) =>
                        Eff::service(Logger::class)
                            ->flatMap(fn($log) => $log->error("Processing error: {$e->getMessage()}"))
                            ->map(fn() => "Order processing failed")
                    );
            
            // Test successful order
            $validOrder = [
                'id' => 123,
                'items' => [
                    ['name' => 'Widget', 'price' => 10, 'qty' => 2],
                    ['name' => 'Gadget', 'price' => 15, 'qty' => 1]
                ]
            ];
            
            $workflow = $processOrder($validOrder);
            $result = Run::sync($appLayer->provideTo($workflow));
            
            expect($result)->toBe('Order #123 completed successfully')
                ->and($logger->logs)->toHaveCount(2);
            
            // Reset logger
            $logger->logs = [];
            
            // Test order with validation error
            $invalidOrder = ['id' => 124, 'items' => []];
            $workflow2 = $processOrder($invalidOrder);
            $result2 = Run::sync($appLayer->provideTo($workflow2));
            
            expect($result2)->toBe('Order validation failed')
                ->and($logger->logs)->toHaveCount(2)
                ->and($logger->logs[1]['level'])->toBe('error');
        });
    });
});