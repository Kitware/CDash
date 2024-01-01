cmake_minimum_required(VERSION 3.25)
project(Simple2)
include(CTest)

add_executable(simple simple.cxx)
add_test(simple simple)
