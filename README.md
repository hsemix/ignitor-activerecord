# Laravel Like ActiveRecord Implementation for CodeIgniter 4

This is a database ActiveRecord Implementation for CodeIgniter 4.

## Installation

You can install the package via composer:

```bash
composer require ignitor/activerecord:^1.0@dev
```

## Usage

### Create a Model

```php
<?php

namespace App\Models;

use Ignitor\ActiveRecord\Model;

class User extends Model
{
    
}
```

### Inserting a record

```php
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();
```

### Updating a record

```php
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();
```

### Deleting a record

```php
$user = User::find(1);
$user->delete();
```

### Querying a record

```php
$user = User::query()->where('name', 'John Doe')->first();
```

### Querying a record with conditions

```php
$user = User::query()->where('name', 'John Doe')->where('email', 'john@example.com')->first();
```

### Querying a record with order

```php
$user = User::query()->orderBy('name', 'desc')->first(); // DESC
```

### Querying a record with limit

```php
$user = User::query()->limit(10)->get();
```

### Querying a record with offset

```php
$user = User::query()->offset(10)->get();
```

### Querying a record with group

```php
$user = User::query()->groupBy('name')->get();
```

### Querying a record with join

```php
$user = User::query()->join('posts', 'users.id', '=', 'posts.user_id')->get();
```

### Querying a record with left join

```php
$user = User::query()->leftJoin('posts', 'users.id', '=', 'posts.user_id')->get();
```

### Querying a record with right join

```php
$user = User::query()->rightJoin('posts', 'users.id', '=', 'posts.user_id')->get();
```

### Querying a record with inner join

```php
$user = User::query()->innerJoin('posts', 'users.id', '=', 'posts.user_id')->get();
```

### Querying a record with cross join

```php
$user = User::query()->crossJoin('posts', 'users.id', '=', 'posts.user_id')->get();
```


### Querying a record with raw query

```php
$user = User::raw('SELECT * FROM users WHERE name = ?', ['John Doe'])->get();
```

### Querying a record with raw query with bindings

```php
$user = User::raw('SELECT * FROM users WHERE name = ?', ['John Doe'])->get();
```

### Querying a record with exists

```php
$user = User::query()->whereExists(function ($query) {
    $query->select('id')->from('posts')->where('user_id', 1);
})->get();
```

### Querying a record with not exists

```php
$user = User::query()->whereNotExists(function ($query) {
    $query->select('id')->from('posts')->where('user_id', 1);
})->get();
```


### Querying a record with in

```php
$user = User::query()->whereIn('id', [1, 2, 3])->get();
```

### Querying a record with not in

```php
$user = User::query()->whereNotIn('id', [1, 2, 3])->get();
```

### Querying a record with between

```php
$user = User::query()->whereBetween('id', [1, 100])->get();
```

### Querying a record with not between

```php    
$user = User::query()->whereNotBetween('id', [1, 100])->get();
```

### Querying a record with like

```php
$user = User::query()->whereLike('name', '%John%')->get();
```

### Querying a record with not like

```php
$user = User::query()->whereNotLike('name', '%John%')->get();
```

### Querying a record with ilike

```php
$user = User::query()->whereIlike('name', '%John%')->get();
```

### Querying a record with not ilike

```php
$user = User::query()->whereNotIlike('name', '%John%')->get();
```

### Querying a record with is null

```php
$user = User::query()->whereNull('name')->get();
```

### Querying a record with is not null

```php
$user = User::query()->whereNotNull('name')->get();
```

### Querying a record with exists

```php
$user = User::query()->whereExists(function ($query) {
    $query->select('id')->from('posts')->where('user_id', 1);
})->get();
```

### Querying a record with not exists

```php
$user = User::query()->whereNotExists(function ($query) {
    $query->select('id')->from('posts')->where('user_id', 1);
})->get();
```

### Querying a record with having exists

```php
$user = User::query()->whereHavingExists(function ($query) {
    $query->select('id')->from('posts')->where('user_id', 1);
})->get();
```

### Querying a record with having not exists

```php
$user = User::query()->whereHavingNotExists(function ($query) {
    $query->select('id')->from('posts')->where('user_id', 1);
})->get();
```

### Defining a relationship (one to one)

```php
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
```

### Defining a reverse relationship (one to one) 

```php
class User extends Model
{
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
```

### Defining a relationship (one to many)

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

### Defining a relationship with conditions

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class)->where('published', true);
    }
}
```


### Defining a relationship with limit

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class)->limit(10)->offset(10)->groupBy('created_at')->orderBy('created_at', 'desc');
    }
}
```


### Defining a relationship with join

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class)->join('comments', 'posts.id', '=', 'comments.post_id');
    }
}
```

### Defining a morph relationship (one to one)

```php
class User extends Model
{
    public function profile()
    {
        return $this->morphOne(Profile::class, 'profileable');
    }
}
```

### Defining a morph relationship (one to many)

```php
class User extends Model
{
    public function posts()
    {
        return $this->morphMany(Post::class, 'postable');
    }
}
```

### Reverse defining a morph relationship (one to one)

```php
class Photo extends Model
{
    public function imageable()
    {
        return $this->morphTo();
    }
}
```

### Querying a record with a relationship (Lazy Loading)

```php
$user = User::query()->with('profile')->first();
```

### Querying a record with a relationship with conditions (Lazy Loading)

```php
$user = User::query()->where('name', 'John Doe')->first();

// access the relationship
$user->profile;
```

### Querying a record with a relationship (Eager Loading)

```php
$user = User::query()->where('name', 'John Doe')->with('profile')->first();

// access the relationship
$user->profile;
```

### Working with complex queries without the query builder

```php
$user = User::query()->where(function ($query) {
    $query->where('name', 'John Doe');
    $query->orWhere('email', 'john@example.com');
})->first();

//
```