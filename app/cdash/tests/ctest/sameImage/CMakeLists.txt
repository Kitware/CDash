cmake_minimum_required(VERSION 3.20)
project(SameImage)
include(CTest)

add_executable(sameImage sameImage.cxx)
add_test(sameImage sameImage smile.gif smile.gif)
