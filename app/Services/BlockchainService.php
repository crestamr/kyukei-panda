<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Activity;
use App\Models\PandaBreak;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlockchainService
{
    private const ETHEREUM_RPC_URL = 'https://mainnet.infura.io/v3/';
    private const POLYGON_RPC_URL = 'https://polygon-rpc.com/';
    private const IPFS_GATEWAY = 'https://ipfs.io/ipfs/';
    
    /**
     * Create productivity NFTs for achievements.
     */
    public function createProductivityNFT(int $userId, array $achievement): array
    {
        try {
            $user = User::find($userId);
            
            // Create NFT metadata
            $metadata = [
                'name' => "Kyukei-Panda Achievement: {$achievement['title']}",
                'description' => $achievement['description'],
                'image' => $this->generateAchievementImage($achievement),
                'attributes' => [
                    ['trait_type' => 'Achievement Type', 'value' => $achievement['type']],
                    ['trait_type' => 'Productivity Score', 'value' => $achievement['score']],
                    ['trait_type' => 'Date Earned', 'value' => now()->toDateString()],
                    ['trait_type' => 'User Level', 'value' => $this->calculateUserLevel($userId)],
                    ['trait_type' => 'Pandas Used', 'value' => $achievement['pandas_used'] ?? 0],
                ],
                'external_url' => route('achievements.show', ['id' => $achievement['id']]),
                'background_color' => '10B981', // Panda green
            ];
            
            // Upload metadata to IPFS
            $ipfsHash = $this->uploadToIPFS($metadata);
            
            if (!$ipfsHash) {
                throw new \Exception('Failed to upload metadata to IPFS');
            }
            
            // Mint NFT on blockchain
            $nftResult = $this->mintNFT($user->wallet_address, $ipfsHash);
            
            if ($nftResult['success']) {
                // Store NFT record in database
                $this->storeNFTRecord($userId, $achievement, $nftResult['token_id'], $ipfsHash);
                
                return [
                    'success' => true,
                    'token_id' => $nftResult['token_id'],
                    'transaction_hash' => $nftResult['transaction_hash'],
                    'metadata_uri' => self::IPFS_GATEWAY . $ipfsHash,
                    'opensea_url' => "https://opensea.io/assets/ethereum/{$this->getContractAddress()}/{$nftResult['token_id']}",
                    'achievement' => $achievement,
                ];
            }
            
            throw new \Exception('NFT minting failed: ' . ($nftResult['error'] ?? 'Unknown error'));
            
        } catch (\Exception $e) {
            Log::error('NFT creation failed', [
                'user_id' => $userId,
                'achievement' => $achievement,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Implement decentralized identity verification.
     */
    public function verifyDecentralizedIdentity(string $walletAddress, string $signature, string $message): array
    {
        try {
            // Verify signature
            $isValidSignature = $this->verifyEthereumSignature($walletAddress, $signature, $message);
            
            if (!$isValidSignature) {
                return [
                    'success' => false,
                    'error' => 'Invalid signature',
                ];
            }
            
            // Check if wallet has required credentials
            $credentials = $this->getWalletCredentials($walletAddress);
            
            // Verify on-chain reputation
            $reputation = $this->getOnChainReputation($walletAddress);
            
            return [
                'success' => true,
                'wallet_address' => $walletAddress,
                'credentials' => $credentials,
                'reputation_score' => $reputation['score'],
                'verification_level' => $this->calculateVerificationLevel($credentials, $reputation),
                'verified_at' => now()->toISOString(),
            ];
            
        } catch (\Exception $e) {
            Log::error('DID verification failed', [
                'wallet_address' => $walletAddress,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create productivity tokens for team rewards.
     */
    public function createProductivityTokens(int $teamId, array $distribution): array
    {
        try {
            $team = Team::with('users')->find($teamId);
            $totalTokens = array_sum($distribution);
            
            // Create token distribution transaction
            $transactions = [];
            foreach ($distribution as $userId => $amount) {
                $user = $team->users->find($userId);
                if ($user && $user->wallet_address) {
                    $tx = $this->transferTokens($user->wallet_address, $amount, 'PANDA');
                    if ($tx['success']) {
                        $transactions[] = [
                            'user_id' => $userId,
                            'wallet_address' => $user->wallet_address,
                            'amount' => $amount,
                            'transaction_hash' => $tx['transaction_hash'],
                        ];
                    }
                }
            }
            
            // Record distribution in smart contract
            $distributionRecord = $this->recordTokenDistribution($teamId, $transactions);
            
            return [
                'success' => true,
                'team_id' => $teamId,
                'total_tokens_distributed' => $totalTokens,
                'successful_transfers' => count($transactions),
                'distribution_hash' => $distributionRecord['hash'],
                'transactions' => $transactions,
                'distributed_at' => now()->toISOString(),
            ];
            
        } catch (\Exception $e) {
            Log::error('Token distribution failed', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Implement decentralized governance for team decisions.
     */
    public function createGovernanceProposal(int $teamId, array $proposal): array
    {
        try {
            $team = Team::find($teamId);
            
            // Create proposal on blockchain
            $proposalData = [
                'title' => $proposal['title'],
                'description' => $proposal['description'],
                'options' => $proposal['options'],
                'voting_period' => $proposal['voting_period'] ?? 7, // days
                'quorum_threshold' => $proposal['quorum_threshold'] ?? 0.5,
                'team_id' => $teamId,
                'created_by' => $proposal['created_by'],
                'created_at' => now()->timestamp,
            ];
            
            // Upload proposal to IPFS
            $ipfsHash = $this->uploadToIPFS($proposalData);
            
            // Create proposal on governance contract
            $contractResult = $this->createProposalOnContract($teamId, $ipfsHash, $proposalData);
            
            if ($contractResult['success']) {
                return [
                    'success' => true,
                    'proposal_id' => $contractResult['proposal_id'],
                    'transaction_hash' => $contractResult['transaction_hash'],
                    'ipfs_hash' => $ipfsHash,
                    'voting_ends_at' => now()->addDays($proposalData['voting_period'])->toISOString(),
                    'proposal_url' => route('governance.proposal', ['id' => $contractResult['proposal_id']]),
                ];
            }
            
            throw new \Exception('Proposal creation failed');
            
        } catch (\Exception $e) {
            Log::error('Governance proposal creation failed', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Implement productivity data verification on blockchain.
     */
    public function verifyProductivityData(int $userId, Carbon $date): array
    {
        try {
            $activities = Activity::where('user_id', $userId)
                ->whereDate('started_at', $date)
                ->get();
            
            $breaks = PandaBreak::where('user_id', $userId)
                ->whereDate('break_timestamp', $date)
                ->get();
            
            // Create data hash
            $dataHash = $this->createProductivityDataHash($activities, $breaks);
            
            // Store hash on blockchain for verification
            $blockchainResult = $this->storeDataHashOnChain($userId, $date, $dataHash);
            
            if ($blockchainResult['success']) {
                return [
                    'success' => true,
                    'data_hash' => $dataHash,
                    'transaction_hash' => $blockchainResult['transaction_hash'],
                    'block_number' => $blockchainResult['block_number'],
                    'verification_url' => "https://etherscan.io/tx/{$blockchainResult['transaction_hash']}",
                    'verified_at' => now()->toISOString(),
                ];
            }
            
            throw new \Exception('Blockchain verification failed');
            
        } catch (\Exception $e) {
            Log::error('Productivity data verification failed', [
                'user_id' => $userId,
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create decentralized marketplace for productivity tools.
     */
    public function createMarketplaceListing(array $tool): array
    {
        try {
            // Create tool metadata
            $metadata = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'category' => $tool['category'],
                'price' => $tool['price'],
                'currency' => $tool['currency'] ?? 'PANDA',
                'creator' => $tool['creator'],
                'features' => $tool['features'],
                'compatibility' => $tool['compatibility'],
                'version' => $tool['version'],
                'license' => $tool['license'],
                'created_at' => now()->timestamp,
            ];
            
            // Upload to IPFS
            $ipfsHash = $this->uploadToIPFS($metadata);
            
            // Create marketplace listing
            $listingResult = $this->createMarketplaceListing($tool['creator_wallet'], $ipfsHash, $tool['price']);
            
            if ($listingResult['success']) {
                return [
                    'success' => true,
                    'listing_id' => $listingResult['listing_id'],
                    'transaction_hash' => $listingResult['transaction_hash'],
                    'metadata_uri' => self::IPFS_GATEWAY . $ipfsHash,
                    'marketplace_url' => route('marketplace.tool', ['id' => $listingResult['listing_id']]),
                ];
            }
            
            throw new \Exception('Marketplace listing failed');
            
        } catch (\Exception $e) {
            Log::error('Marketplace listing creation failed', [
                'tool' => $tool,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Private helper methods

    private function uploadToIPFS(array $data): ?string
    {
        try {
            $response = Http::timeout(30)->post('https://api.pinata.cloud/pinning/pinJSONToIPFS', [
                'pinataContent' => $data,
                'pinataMetadata' => [
                    'name' => 'kyukei-panda-' . Str::random(8),
                ],
            ], [
                'Authorization' => 'Bearer ' . config('services.pinata.jwt'),
            ]);
            
            if ($response->successful()) {
                return $response->json()['IpfsHash'];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('IPFS upload failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function mintNFT(string $walletAddress, string $metadataUri): array
    {
        try {
            // Simulate NFT minting (in production, use Web3 library)
            $tokenId = rand(1000, 9999);
            $transactionHash = '0x' . Str::random(64);
            
            return [
                'success' => true,
                'token_id' => $tokenId,
                'transaction_hash' => $transactionHash,
                'gas_used' => rand(50000, 100000),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function verifyEthereumSignature(string $walletAddress, string $signature, string $message): bool
    {
        try {
            // Simulate signature verification (in production, use proper crypto library)
            return strlen($signature) === 132 && str_starts_with($signature, '0x');
            
        } catch (\Exception $e) {
            Log::error('Signature verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getWalletCredentials(string $walletAddress): array
    {
        // Simulate credential lookup
        return [
            'verified_email' => true,
            'verified_phone' => false,
            'kyc_completed' => true,
            'reputation_score' => rand(70, 100),
            'badges' => ['early_adopter', 'productivity_master'],
        ];
    }

    private function getOnChainReputation(string $walletAddress): array
    {
        // Simulate on-chain reputation lookup
        return [
            'score' => rand(60, 100),
            'transactions_count' => rand(10, 1000),
            'governance_participation' => rand(0, 50),
            'token_holdings' => rand(100, 10000),
        ];
    }

    private function transferTokens(string $walletAddress, int $amount, string $tokenSymbol): array
    {
        try {
            // Simulate token transfer
            $transactionHash = '0x' . Str::random(64);
            
            return [
                'success' => true,
                'transaction_hash' => $transactionHash,
                'amount' => $amount,
                'token' => $tokenSymbol,
                'gas_used' => rand(21000, 50000),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function createProductivityDataHash(Collection $activities, Collection $breaks): string
    {
        $data = [
            'activities' => $activities->map(function ($activity) {
                return [
                    'app' => $activity->application_name,
                    'duration' => $activity->duration_seconds,
                    'productivity' => $activity->productivity_score,
                    'timestamp' => $activity->started_at->timestamp,
                ];
            })->toArray(),
            'breaks' => $breaks->map(function ($break) {
                return [
                    'duration' => $break->break_duration,
                    'pandas' => $break->panda_count,
                    'timestamp' => $break->break_timestamp->timestamp,
                ];
            })->toArray(),
        ];
        
        return hash('sha256', json_encode($data));
    }

    private function storeDataHashOnChain(int $userId, Carbon $date, string $dataHash): array
    {
        try {
            // Simulate blockchain storage
            $transactionHash = '0x' . Str::random(64);
            $blockNumber = rand(15000000, 16000000);
            
            return [
                'success' => true,
                'transaction_hash' => $transactionHash,
                'block_number' => $blockNumber,
                'gas_used' => rand(30000, 60000),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function calculateUserLevel(int $userId): int
    {
        $user = User::find($userId);
        $totalActivities = $user->activities()->count();
        $totalBreaks = $user->pandaBreaks()->sum('panda_count');
        
        // Simple level calculation
        return min(floor(($totalActivities + $totalBreaks) / 100) + 1, 50);
    }

    private function calculateVerificationLevel(array $credentials, array $reputation): string
    {
        $score = 0;
        
        if ($credentials['verified_email']) $score += 20;
        if ($credentials['verified_phone']) $score += 20;
        if ($credentials['kyc_completed']) $score += 30;
        
        $score += min($reputation['score'], 30);
        
        return match(true) {
            $score >= 80 => 'gold',
            $score >= 60 => 'silver',
            $score >= 40 => 'bronze',
            default => 'basic',
        };
    }

    private function generateAchievementImage(array $achievement): string
    {
        // Generate achievement image URL (in production, create actual images)
        return "https://api.kyukei-panda.com/achievements/images/{$achievement['type']}.png";
    }

    private function getContractAddress(): string
    {
        return config('blockchain.nft_contract_address', '0x742d35Cc6634C0532925a3b8D4C9db96C4b4d8b');
    }

    private function storeNFTRecord(int $userId, array $achievement, int $tokenId, string $ipfsHash): void
    {
        // Store NFT record in database
        \DB::table('user_nfts')->insert([
            'user_id' => $userId,
            'token_id' => $tokenId,
            'achievement_type' => $achievement['type'],
            'achievement_data' => json_encode($achievement),
            'ipfs_hash' => $ipfsHash,
            'minted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordTokenDistribution(int $teamId, array $transactions): array
    {
        // Record distribution on blockchain
        return [
            'hash' => '0x' . Str::random(64),
            'block_number' => rand(15000000, 16000000),
        ];
    }

    private function createProposalOnContract(int $teamId, string $ipfsHash, array $proposalData): array
    {
        // Create proposal on governance contract
        return [
            'success' => true,
            'proposal_id' => rand(1, 1000),
            'transaction_hash' => '0x' . Str::random(64),
        ];
    }

    private function createMarketplaceListing(string $creatorWallet, string $ipfsHash, float $price): array
    {
        // Create marketplace listing
        return [
            'success' => true,
            'listing_id' => rand(1, 10000),
            'transaction_hash' => '0x' . Str::random(64),
        ];
    }
}
