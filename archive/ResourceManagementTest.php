<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Scope;

describe('Resource Management', function () {
    
    describe('automatic resource cleanup', function () {
        it('ensures resources are cleaned up on success', function () {
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $workflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('resource_acquired');
                        return 'database_connection';
                    }),
                    release: fn($resource) => Eff::sync(function() use ($tracker, $resource) {
                        $tracker->track("resource_released:$resource");
                    })
                )->flatMap(fn($resource) => Eff::sync(function() use ($resource, $tracker) {
                    $tracker->track("resource_used:$resource");
                    return "Processed with $resource";
                }));
            });
            
            $result = Eff::runSync($workflow);
            
            expect($result)->toBe('Processed with database_connection')
                ->and($tracker->events)->toBe([
                    'resource_acquired',
                    'resource_used:database_connection',
                    'resource_released:database_connection'
                ]);
        });
        
        it('ensures resources are cleaned up on failure', function () {
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $workflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('file_opened');
                        return 'file_handle';
                    }),
                    release: fn($resource) => Eff::sync(function() use ($tracker, $resource) {
                        $tracker->track("file_closed:$resource");
                    })
                )->flatMap(fn($resource) => Eff::sync(function() use ($tracker, $resource) {
                    $tracker->track("processing:$resource");
                    throw new \RuntimeException('Processing failed');
                }));
            });
            
            $result = Eff::runSafely($workflow);
            
            expect($result->isLeft())->toBeTrue()
                ->and($tracker->events)->toBe([
                    'file_opened',
                    'processing:file_handle',
                    'file_closed:file_handle'
                ]);
        });
    });
    
    describe('nested resource management', function () {
        it('handles multiple nested resources correctly', function () {
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $workflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('connection_opened');
                        return 'db_connection';
                    }),
                    release: fn($conn) => Eff::sync(function() use ($tracker, $conn) {
                        $tracker->track("connection_closed:$conn");
                    })
                )->flatMap(function($connection) use ($scope, $tracker) {
                    return $scope->acquireResource(
                        acquire: Eff::sync(function() use ($tracker, $connection) {
                            $tracker->track("transaction_started:$connection");
                            return 'transaction';
                        }),
                        release: fn($tx) => Eff::sync(function() use ($tracker, $tx) {
                            $tracker->track("transaction_committed:$tx");
                        })
                    )->flatMap(function($transaction) use ($connection, $tracker) {
                        return Eff::sync(function() use ($connection, $transaction, $tracker) {
                            $tracker->track("query_executed:$connection:$transaction");
                            return "Result from $connection in $transaction";
                        });
                    });
                });
            });
            
            $result = Eff::runSync($workflow);
            
            expect($result)->toBe('Result from db_connection in transaction')
                ->and($tracker->events)->toBe([
                    'connection_opened',
                    'transaction_started:db_connection',
                    'query_executed:db_connection:transaction',
                    'transaction_committed:transaction',
                    'connection_closed:db_connection'
                ]);
        });
        
        it('cleans up all resources even if inner resource fails', function () {
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $workflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('outer_acquired');
                        return 'outer_resource';
                    }),
                    release: fn($resource) => Eff::sync(function() use ($tracker, $resource) {
                        $tracker->track("outer_released:$resource");
                    })
                )->flatMap(function($outerResource) use ($scope, $tracker) {
                    return $scope->acquireResource(
                        acquire: Eff::sync(function() use ($tracker) {
                            $tracker->track('inner_acquired');
                            throw new \RuntimeException('Failed to acquire inner resource');
                        }),
                        release: fn($resource) => Eff::sync(function() use ($tracker) {
                            $tracker->track('inner_released'); // Should not be called
                        })
                    );
                });
            });
            
            $result = Eff::runSafely($workflow);
            
            expect($result->isLeft())->toBeTrue()
                ->and($tracker->events)->toBe([
                    'outer_acquired',
                    'inner_acquired',
                    'outer_released:outer_resource'
                ]);
        });
    });
    
    describe('resource sharing and reuse', function () {
        it('allows safe resource sharing within scope', function () {
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $workflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('connection_pool_created');
                        return new class {
                            public int $usageCount = 0;
                            public function use(): string {
                                $this->usageCount++;
                                return "usage_{$this->usageCount}";
                            }
                        };
                    }),
                    release: fn($pool) => Eff::sync(function() use ($tracker, $pool) {
                        $tracker->track("connection_pool_closed:used_{$pool->usageCount}_times");
                    })
                )->flatMap(function($pool) use ($tracker) {
                    // Use the resource multiple times
                    $op1 = Eff::sync(function() use ($pool, $tracker) {
                        $result = $pool->use();
                        $tracker->track("operation_1:$result");
                        return $result;
                    });
                    
                    $op2 = Eff::sync(function() use ($pool, $tracker) {
                        $result = $pool->use();
                        $tracker->track("operation_2:$result");
                        return $result;
                    });
                    
                    return Eff::allInParallel([$op1, $op2]);
                });
            });
            
            $result = Eff::runSync($workflow);
            
            expect($result)->toBe(['usage_1', 'usage_2'])
                ->and($tracker->events)->toBe([
                    'connection_pool_created',
                    'operation_1:usage_1',
                    'operation_2:usage_2',
                    'connection_pool_closed:used_2_times'
                ]);
        });
    });
    
    describe('library developer scenarios', function () {
        it('manages HTTP client connections', function () {
            // Simulate HTTP client resource management for PolyglotPHP
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $httpClientWorkflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('http_client_initialized');
                        return new class {
                            public array $requests = [];
                            public function post(string $url, array $data): array {
                                $this->requests[] = ['method' => 'POST', 'url' => $url, 'data' => $data];
                                return ['status' => 200, 'body' => json_encode(['response' => 'success'])];
                            }
                        };
                    }),
                    release: fn($client) => Eff::sync(function() use ($tracker, $client) {
                        $tracker->track("http_client_closed:made_{$client->requests->count()}_requests");
                    })
                )->flatMap(function($client) use ($tracker) {
                    // Multiple API calls using the same client
                    $call1 = Eff::sync(function() use ($client, $tracker) {
                        $response = $client->post('https://api.openai.com/v1/completions', ['prompt' => 'Hello']);
                        $tracker->track('openai_api_called');
                        return $response;
                    });
                    
                    $call2 = Eff::sync(function() use ($client, $tracker) {
                        $response = $client->post('https://api.anthropic.com/v1/messages', ['prompt' => 'Hi']);
                        $tracker->track('anthropic_api_called');
                        return $response;
                    });
                    
                    return Eff::allInParallel([$call1, $call2])
                        ->map(fn($responses) => ['openai' => $responses[0], 'anthropic' => $responses[1]]);
                });
            });
            
            $result = Eff::runSync($httpClientWorkflow);
            
            expect($result['openai']['status'])->toBe(200)
                ->and($result['anthropic']['status'])->toBe(200)
                ->and($tracker->events)->toContain('http_client_initialized')
                ->and($tracker->events)->toContain('openai_api_called')
                ->and($tracker->events)->toContain('anthropic_api_called');
        });
        
        it('manages database transactions safely', function () {
            // Simulate database transaction management
            $tracker = new class {
                public array $events = [];
                public array $data = [];
                public function track(string $event): void { $this->events[] = $event; }
                public function store(string $key, mixed $value): void { $this->data[$key] = $value; }
            };
            
            $databaseWorkflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('database_connection_opened');
                        return new class {
                            private array $transactionData = [];
                            public function beginTransaction(): void {
                                $this->transactionData = [];
                            }
                            public function insert(string $table, array $data): void {
                                $this->transactionData[] = ['table' => $table, 'data' => $data];
                            }
                            public function commit(): void {
                                foreach ($this->transactionData as $operation) {
                                    // Simulate actual database write
                                }
                            }
                            public function rollback(): void {
                                $this->transactionData = [];
                            }
                        };
                    }),
                    release: fn($db) => Eff::sync(function() use ($tracker, $db) {
                        $tracker->track('database_connection_closed');
                    })
                )->flatMap(function($db) use ($scope, $tracker) {
                    return $scope->acquireResource(
                        acquire: Eff::sync(function() use ($db, $tracker) {
                            $db->beginTransaction();
                            $tracker->track('transaction_started');
                            return $db;
                        }),
                        release: fn($transactionDb) => Eff::sync(function() use ($transactionDb, $tracker) {
                            $transactionDb->commit();
                            $tracker->track('transaction_committed');
                        })
                    )->flatMap(function($transactionDb) use ($tracker) {
                        return Eff::sync(function() use ($transactionDb, $tracker) {
                            $transactionDb->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
                            $transactionDb->insert('profiles', ['user_id' => 1, 'bio' => 'Developer']);
                            $tracker->track('data_inserted');
                            return 'Transaction completed successfully';
                        });
                    });
                });
            });
            
            $result = Eff::runSync($databaseWorkflow);
            
            expect($result)->toBe('Transaction completed successfully')
                ->and($tracker->events)->toBe([
                    'database_connection_opened',
                    'transaction_started',
                    'data_inserted',
                    'transaction_committed',
                    'database_connection_closed'
                ]);
        });
        
        it('handles file processing with automatic cleanup', function () {
            // Simulate file processing for InstructorPHP
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $fileProcessingWorkflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('temp_file_created');
                        return new class {
                            public string $content = '';
                            public function write(string $data): void {
                                $this->content .= $data;
                            }
                            public function read(): string {
                                return $this->content;
                            }
                        };
                    }),
                    release: fn($file) => Eff::sync(function() use ($tracker, $file) {
                        $tracker->track("temp_file_deleted:size_{$file->content->length()}");
                    })
                )->flatMap(function($tempFile) use ($tracker) {
                    // Process LLM response and write to temp file
                    return Eff::sync(function() use ($tempFile, $tracker) {
                        $llmResponse = '{"name": "John Doe", "age": 30, "skills": ["PHP", "Python"]}';
                        $tempFile->write($llmResponse);
                        $tracker->track('llm_response_written');
                        return $tempFile;
                    })->flatMap(function($tempFile) use ($tracker) {
                        // Validate and parse the content
                        return Eff::sync(function() use ($tempFile, $tracker) {
                            $content = $tempFile->read();
                            $parsed = json_decode($content, true);
                            
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new \RuntimeException('Invalid JSON in temp file');
                            }
                            
                            $tracker->track('content_validated');
                            return $parsed;
                        });
                    });
                });
            });
            
            $result = Eff::runSync($fileProcessingWorkflow);
            
            expect($result['name'])->toBe('John Doe')
                ->and($result['skills'])->toBe(['PHP', 'Python'])
                ->and($tracker->events)->toContain('temp_file_created')
                ->and($tracker->events)->toContain('llm_response_written')
                ->and($tracker->events)->toContain('content_validated');
        });
        
        it('manages streaming resources with proper cleanup', function () {
            // Simulate streaming response processing
            $tracker = new class {
                public array $events = [];
                public function track(string $event): void { $this->events[] = $event; }
            };
            
            $streamingWorkflow = Eff::scoped(function(Scope $scope) use ($tracker) {
                return $scope->acquireResource(
                    acquire: Eff::sync(function() use ($tracker) {
                        $tracker->track('stream_opened');
                        return new class {
                            private array $chunks = [
                                '{"partial": "res',
                                'ponse", "cont',
                                'ent": "streaming data"}'
                            ];
                            private int $position = 0;
                            
                            public function readChunk(): ?string {
                                if ($this->position < count($this->chunks)) {
                                    return $this->chunks[$this->position++];
                                }
                                return null;
                            }
                            
                            public function isEof(): bool {
                                return $this->position >= count($this->chunks);
                            }
                        };
                    }),
                    release: fn($stream) => Eff::sync(function() use ($tracker, $stream) {
                        $tracker->track('stream_closed');
                    })
                )->flatMap(function($stream) use ($tracker) {
                    return Eff::sync(function() use ($stream, $tracker) {
                        $completeContent = '';
                        
                        while (!$stream->isEof()) {
                            $chunk = $stream->readChunk();
                            if ($chunk !== null) {
                                $completeContent .= $chunk;
                                $tracker->track("chunk_read:length_{$chunk->length()}");
                            }
                        }
                        
                        $parsed = json_decode($completeContent, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new \RuntimeException('Invalid streaming JSON');
                        }
                        
                        $tracker->track('stream_processing_complete');
                        return $parsed;
                    });
                });
            });
            
            $result = Eff::runSync($streamingWorkflow);
            
            expect($result['partial'])->toBe('response')
                ->and($result['content'])->toBe('streaming data')
                ->and($tracker->events)->toContain('stream_opened')
                ->and($tracker->events)->toContain('stream_processing_complete')
                ->and($tracker->events)->toContain('stream_closed');
        });
    });
});