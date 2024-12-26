# 실습 과제 - Day4

## 설치법

1. `.env` 설정
2. `\App\Services\Connections\MdwConnection` 설정
3. `php artisan migrate` 실행
4. `php artisan orange:test` 실행

## 테스트 기록

### 최초 작성 코드로 테스트

1000개마다 로그 기록

```shell
16.402807 // app/Console/Commands/test.php:164
33.538999 // app/Console/Commands/test.php:164
50.352262 // app/Console/Commands/test.php:164
67.480664 // app/Console/Commands/test.php:164
84.477739 // app/Console/Commands/test.php:164
101.426085 // app/Console/Commands/test.php:164
118.400739 // app/Console/Commands/test.php:164
135.56749 // app/Console/Commands/test.php:164
152.732678 // app/Console/Commands/test.php:164
169.912189 // app/Console/Commands/test.php:164
187.632261 // app/Console/Commands/test.php:164
205.805444 // app/Console/Commands/test.php:164
223.101575 // app/Console/Commands/test.php:164
240.752262 // app/Console/Commands/test.php:164
257.799721 // app/Console/Commands/test.php:164
275.084833 // app/Console/Commands/test.php:164
292.521097 // app/Console/Commands/test.php:164
309.732434 // app/Console/Commands/test.php:164
```

### 요구사항 재확인 후 테스트

```shell
"total time : 1274.8751"
```

### 인덱스 추가 후

#### 이미 seed가 된 상태에서 커맨드를 통해 Processed만 추가했을 경우

```shell
"total time : 142.482294"
```

#### seed도 함께 진행했을 경우

```shell
"total time : 309.774007"
```
