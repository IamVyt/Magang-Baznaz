-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 23, 2026 at 04:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sim_keuangan`
--

-- --------------------------------------------------------

--
-- Table structure for table `penerima`
--

CREATE TABLE `penerima` (
  `id` int(11) NOT NULL,
  `nama_penerima` varchar(100) DEFAULT NULL,
  `jumlah_keluar` decimal(15,2) DEFAULT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penerima`
--

INSERT INTO `penerima` (`id`, `nama_penerima`, `jumlah_keluar`, `tanggal`) VALUES
(1, 'bansos', 230000.00, '2026-01-23 03:28:29');

-- --------------------------------------------------------

--
-- Table structure for table `penyumbang`
--

CREATE TABLE `penyumbang` (
  `id` int(11) NOT NULL,
  `nama_penyumbang` varchar(100) DEFAULT NULL,
  `jumlah_diterima` decimal(15,2) DEFAULT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penyumbang`
--

INSERT INTO `penyumbang` (`id`, `nama_penyumbang`, `jumlah_diterima`, `tanggal`) VALUES
(1, 'vivit', 5000000.00, '2026-01-23 03:28:10');

-- --------------------------------------------------------

--
-- Table structure for table `saldo`
--

CREATE TABLE `saldo` (
  `id` int(11) NOT NULL,
  `jumlah_total` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saldo`
--

INSERT INTO `saldo` (`id`, `jumlah_total`) VALUES
(1, 4770000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama_lengkap`, `username`, `password`) VALUES
(1, 'Administrator', 'Hanif', '$2y$10$vjJy.UVYoIi.Sx0RTIamdePGrwzTSo.eNQQ4LGAcFPJtDXtAE5NG2');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `penerima`
--
ALTER TABLE `penerima`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `penyumbang`
--
ALTER TABLE `penyumbang`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `saldo`
--
ALTER TABLE `saldo`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `penerima`
--
ALTER TABLE `penerima`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `penyumbang`
--
ALTER TABLE `penyumbang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
