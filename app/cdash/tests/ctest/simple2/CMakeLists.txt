cmake_minimum_required(VERSION 3.22)
project(Simple2)
include(CTest)

add_executable(simple simple.cxx)
add_test(simple simple)
