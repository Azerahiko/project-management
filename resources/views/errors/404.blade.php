@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan')
@section('code', '404 - Not Found')
@section('header', 'Halaman Tidak Ditemukan')
@section('message', $exception->getMessage() ?: 'Halaman yang Anda tuju tidak ditemukan atau tautan salah.')
