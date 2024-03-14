cmake_minimum_required(VERSION 3.20)
project(Simple)
include(CTest)

add_executable(simple simple.cxx)
add_test(simple simple)
