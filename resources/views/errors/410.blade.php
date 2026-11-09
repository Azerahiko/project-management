@extends('errors.layout')

@section('title', 'Tautan Tidak Berlaku')
@section('code', '410 - Gone')
@section('header', 'Tautan Tidak Berlaku')
@section('message', $exception->getMessage() ?: 'Undangan atau tautan aktivasi ini sudah kedaluwarsa atau telah digunakan sebelumnya. Silakan hubungi administrator Anda untuk mendapatkan tautan baru.')
